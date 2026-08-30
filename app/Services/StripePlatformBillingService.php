<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use RuntimeException;

class StripePlatformBillingService
{
    public function __construct(private readonly Client $client)
    {
    }

    public function createCheckoutSession(Company $company, PlatformSubscriptionPlan $plan, bool $autoRenew, string $successUrl, string $cancelUrl): array
    {
        $subscription = $company->subscription()->firstOrCreate([], [
            'platform_subscription_plan_id' => $plan->id,
            'status' => 'incomplete',
            'auto_renew' => $autoRenew,
            'is_enabled' => true,
        ]);

        $priceId = $this->ensurePlanPrice($plan);
        $payload = [
            'mode' => 'subscription',
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $company->id,
            'line_items[0][quantity]' => 1,
            'line_items[0][price]' => $priceId,
            'metadata[company_id]' => (string) $company->id,
            'metadata[plan_id]' => (string) $plan->id,
            'metadata[auto_renew]' => $autoRenew ? '1' : '0',
            'metadata[purchase_type]' => 'subscription',
            'subscription_data[metadata][company_id]' => (string) $company->id,
            'subscription_data[metadata][plan_id]' => (string) $plan->id,
        ];

        if ($subscription->stripe_customer_id) {
            $payload['customer'] = $subscription->stripe_customer_id;
        } else {
            $payload['customer_email'] = $company->email ?: $company->owners()->value('email');
        }

        if (! $autoRenew) {
            $payload['subscription_data[cancel_at_period_end]'] = 'true';
        }

        $session = $this->request('POST', '/v1/checkout/sessions', $payload);

        $subscription->update([
            'platform_subscription_plan_id' => $plan->id,
            'pending_platform_subscription_plan_id' => null,
            'pending_plan_effective_at' => null,
            'stripe_subscription_id' => null,
            'stripe_subscription_schedule_id' => null,
            'stripe_checkout_session_id' => Arr::get($session, 'id'),
            'status' => 'incomplete',
            'auto_renew' => $autoRenew,
            'is_enabled' => true,
        ]);

        return $session;
    }

    public function upgradeSubscription(CompanySubscription $subscription, PlatformSubscriptionPlan $targetPlan): array
    {
        if (! $subscription->stripe_subscription_id) {
            throw new RuntimeException('The current subscription is missing its Stripe subscription ID.');
        }

        $this->assertCompanyFitsPlan($subscription, $targetPlan);

        $remote = $this->retrieveSubscription($subscription->stripe_subscription_id);
        if ((string) ($remote['status'] ?? '') !== 'active') {
            throw new RuntimeException('Immediate paid upgrades require an active Stripe subscription that is not in a trial.');
        }

        if (($remote['collection_method'] ?? 'charge_automatically') !== 'charge_automatically') {
            throw new RuntimeException('Immediate upgrades require automatic Stripe payment collection.');
        }

        if (! empty($remote['pending_update'])) {
            throw new RuntimeException('A Stripe subscription update is already awaiting payment. Resolve it before changing plans again.');
        }

        if ($this->stripeScheduleId($remote) !== '') {
            throw new RuntimeException('A legacy Stripe subscription schedule is attached. Clear it before changing plans.');
        }

        $item = Arr::get($remote, 'items.data.0');
        if (! is_array($item) || empty($item['id'])) {
            throw new RuntimeException('Stripe did not return the subscription item required for this upgrade.');
        }

        $currentCurrency = strtolower((string) Arr::get($item, 'price.currency', ''));
        if ($currentCurrency !== '' && $currentCurrency !== strtolower((string) $targetPlan->currency)) {
            throw new RuntimeException('Mid-cycle plan changes cannot switch subscription currency.');
        }

        $targetPriceId = $this->ensurePlanPrice($targetPlan);
        $payload = [
            'payment_behavior' => 'pending_if_incomplete',
            'proration_behavior' => 'always_invoice',
            'items[0][id]' => (string) $item['id'],
            'items[0][price]' => $targetPriceId,
            'items[0][quantity]' => max(1, (int) ($item['quantity'] ?? 1)),
            'expand[0]' => 'latest_invoice.payment_intent',
        ];
        $idempotencyKey = $this->mutationKey('upgrade', $subscription->stripe_subscription_id, $payload, [
            $this->priceIdFromSubscription($remote),
            (string) ($remote['current_period_end'] ?? ''),
        ]);

        $updated = $this->request(
            'POST',
            '/v1/subscriptions/'.$subscription->stripe_subscription_id,
            $payload,
            $idempotencyKey
        );

        if (! empty($updated['pending_update'])) {
            $invoice = is_array($updated['latest_invoice'] ?? null) ? $updated['latest_invoice'] : [];

            return [
                'applied' => false,
                'subscription' => $updated,
                'invoice_url' => $invoice['hosted_invoice_url'] ?? null,
                'payment_intent_status' => is_array($invoice['payment_intent'] ?? null)
                    ? ($invoice['payment_intent']['status'] ?? null)
                    : null,
            ];
        }

        if ($this->priceIdFromSubscription($updated) !== $targetPriceId) {
            throw new RuntimeException('Stripe did not confirm the requested upgraded price. Local entitlements were not changed.');
        }

        $updated = $this->request('POST', '/v1/subscriptions/'.$subscription->stripe_subscription_id, [
            'metadata[company_id]' => (string) $subscription->company_id,
            'metadata[plan_id]' => (string) $targetPlan->id,
            'metadata[pending_plan_id]' => '',
            'metadata[pending_plan_effective_at]' => '',
        ], $idempotencyKey.'-metadata');

        return [
            'applied' => true,
            'subscription' => $updated,
            'invoice_url' => null,
            'payment_intent_status' => null,
        ];
    }

    /**
     * Same-interval downgrades use the normal Subscription API instead of a
     * Subscription Schedule. Stripe switches the recurring Price with no
     * proration, so there is no immediate credit/refund/charge and the next
     * renewal uses the lower Price. MuebleDesk keeps the already-paid current
     * plan entitlements locally until current_period_end.
     */
    public function deferDowngrade(CompanySubscription $subscription, PlatformSubscriptionPlan $targetPlan): array
    {
        if (! $subscription->stripe_subscription_id || ! $subscription->plan) {
            throw new RuntimeException('An active local and Stripe subscription are required for a downgrade.');
        }

        $this->assertCompanyFitsPlan($subscription, $targetPlan);

        if (! $targetPlan->sameBillingIntervalAs($subscription->plan)) {
            throw new RuntimeException('Changing billing intervals mid-subscription is disabled. Cancel at period end, then purchase the new interval after the current period finishes.');
        }

        $remote = $this->retrieveSubscription($subscription->stripe_subscription_id);
        if ((string) ($remote['status'] ?? '') !== 'active') {
            throw new RuntimeException('Only an active Stripe subscription can be downgraded.');
        }
        if ((bool) ($remote['cancel_at_period_end'] ?? false)) {
            throw new RuntimeException('This subscription is already set to cancel at period end. Resume it before changing plans.');
        }
        if (! empty($remote['pending_update'])) {
            throw new RuntimeException('A Stripe subscription update is already awaiting payment. Resolve it before changing plans again.');
        }
        if ($this->stripeScheduleId($remote) !== '') {
            throw new RuntimeException('A legacy Stripe subscription schedule is attached. Clear it before changing plans.');
        }

        $item = Arr::get($remote, 'items.data.0');
        if (! is_array($item) || empty($item['id'])) {
            throw new RuntimeException('Stripe did not return the subscription item required for this downgrade.');
        }

        $currentCurrency = strtolower((string) Arr::get($item, 'price.currency', ''));
        if ($currentCurrency !== '' && $currentCurrency !== strtolower((string) $targetPlan->currency)) {
            throw new RuntimeException('Plan changes cannot switch subscription currency.');
        }

        $currentEnd = (int) ($remote['current_period_end'] ?? 0);
        if ($currentEnd <= time()) {
            throw new RuntimeException('Stripe did not return a valid future renewal date.');
        }

        $targetPriceId = $this->ensurePlanPrice($targetPlan);
        $payload = [
            'proration_behavior' => 'none',
            'items[0][id]' => (string) $item['id'],
            'items[0][price]' => $targetPriceId,
            'items[0][quantity]' => max(1, (int) ($item['quantity'] ?? 1)),
            'metadata[company_id]' => (string) $subscription->company_id,
            // Keep metadata.plan_id on the entitlement plan until renewal.
            'metadata[plan_id]' => (string) $subscription->platform_subscription_plan_id,
            'metadata[pending_plan_id]' => (string) $targetPlan->id,
            'metadata[pending_plan_effective_at]' => (string) $currentEnd,
        ];

        $updated = $this->request(
            'POST',
            '/v1/subscriptions/'.$subscription->stripe_subscription_id,
            $payload,
            $this->mutationKey('downgrade', $subscription->stripe_subscription_id, $payload, [
                $this->priceIdFromSubscription($remote),
                (string) $currentEnd,
            ])
        );

        if ($this->priceIdFromSubscription($updated) !== $targetPriceId) {
            throw new RuntimeException('Stripe did not confirm the requested lower recurring price. No local downgrade was recorded.');
        }

        return [
            'subscription' => $updated,
            'effective_at' => $currentEnd,
        ];
    }

    /** Revert a deferred same-interval downgrade before it becomes effective. */
    public function cancelDeferredPlanChange(CompanySubscription $subscription): bool
    {
        if (! $subscription->stripe_subscription_id || ! $subscription->plan) {
            throw new RuntimeException('The current subscription is missing billing information.');
        }

        if (! $subscription->pending_platform_subscription_plan_id || ! $subscription->pending_plan_effective_at) {
            return false;
        }

        $remote = $this->retrieveSubscription($subscription->stripe_subscription_id);
        if ((string) ($remote['status'] ?? '') !== 'active') {
            throw new RuntimeException('The Stripe subscription is not active.');
        }
        if ($this->stripeScheduleId($remote) !== '') {
            throw new RuntimeException('A legacy Stripe schedule is attached. Clear that legacy schedule first.');
        }

        $item = Arr::get($remote, 'items.data.0');
        if (! is_array($item) || empty($item['id'])) {
            throw new RuntimeException('Stripe did not return the subscription item required to cancel the plan change.');
        }

        $currentPriceId = $this->ensurePlanPrice($subscription->plan);
        if ($this->priceIdFromSubscription($remote) === $currentPriceId) {
            return true;
        }

        $payload = [
            'proration_behavior' => 'none',
            'items[0][id]' => (string) $item['id'],
            'items[0][price]' => $currentPriceId,
            'items[0][quantity]' => max(1, (int) ($item['quantity'] ?? 1)),
            'metadata[company_id]' => (string) $subscription->company_id,
            'metadata[plan_id]' => (string) $subscription->platform_subscription_plan_id,
            'metadata[pending_plan_id]' => '',
            'metadata[pending_plan_effective_at]' => '',
        ];

        $updated = $this->request(
            'POST',
            '/v1/subscriptions/'.$subscription->stripe_subscription_id,
            $payload,
            $this->mutationKey('cancel-plan-change', $subscription->stripe_subscription_id, $payload, [
                $this->priceIdFromSubscription($remote),
            ])
        );

        if ($this->priceIdFromSubscription($updated) !== $currentPriceId) {
            throw new RuntimeException('Stripe did not restore the current recurring price. The pending plan change was not cleared locally.');
        }

        return true;
    }

    /** Standard SaaS cancellation: keep access until the current paid period ends. */
    public function setCancelAtPeriodEnd(CompanySubscription $subscription, bool $cancel): array
    {
        $subscriptionId = (string) $subscription->stripe_subscription_id;
        if ($subscriptionId === '') {
            throw new RuntimeException('The current subscription is missing its Stripe subscription ID.');
        }

        $remote = $this->retrieveSubscription($subscriptionId);
        if (! in_array((string) ($remote['status'] ?? ''), ['active', 'trialing', 'past_due'], true)) {
            throw new RuntimeException('This Stripe subscription cannot change its cancellation state.');
        }
        if ($this->stripeScheduleId($remote) !== '') {
            throw new RuntimeException('A legacy Stripe subscription schedule is attached. Clear it before canceling or resuming the subscription.');
        }

        $payload = ['cancel_at_period_end' => $cancel ? 'true' : 'false'];
        $updated = $this->request(
            'POST',
            '/v1/subscriptions/'.$subscriptionId,
            $payload,
            $this->mutationKey($cancel ? 'cancel-subscription' : 'resume-subscription', $subscriptionId, $payload, [
                (string) ($remote['current_period_end'] ?? ''),
                (string) (int) (bool) ($remote['cancel_at_period_end'] ?? false),
            ])
        );

        if ((bool) ($updated['cancel_at_period_end'] ?? false) !== $cancel) {
            throw new RuntimeException('Stripe did not confirm the requested subscription cancellation state.');
        }

        return $updated;
    }

    /** Legacy cleanup only; normal plan changes no longer create schedules. */
    public function cancelScheduledPlanChange(CompanySubscription $subscription): bool
    {
        $subscriptionId = (string) $subscription->stripe_subscription_id;
        if ($subscriptionId === '') {
            throw new RuntimeException('The current subscription is missing its Stripe subscription ID.');
        }

        $remote = $this->retrieveSubscription($subscriptionId);
        $remoteScheduleId = $this->stripeScheduleId($remote);
        if ($remoteScheduleId === '') {
            return false;
        }

        $this->request(
            'POST',
            '/v1/subscription_schedules/'.$remoteScheduleId.'/release',
            [],
            'muebledesk-legacy-schedule-release-'.$remoteScheduleId
        );

        if ($this->stripeScheduleId($this->retrieveSubscription($subscriptionId)) !== '') {
            throw new RuntimeException('Stripe did not detach the legacy subscription schedule.');
        }

        return true;
    }

    public function ensurePlanPrice(PlatformSubscriptionPlan $plan): string
    {
        if ($plan->stripe_price_id) {
            return (string) $plan->stripe_price_id;
        }

        $productId = (string) ($plan->stripe_product_id ?? '');
        if ($productId === '') {
            $product = $this->request('POST', '/v1/products', [
                'name' => $plan->name,
                'description' => $plan->description ?: 'MuebleDesk subscription plan',
                'metadata[platform_plan_id]' => (string) $plan->id,
            ], 'muebledesk-product-'.$plan->id);
            $productId = (string) ($product['id'] ?? '');

            if ($productId === '') {
                throw new RuntimeException('Stripe did not return a product ID for this plan.');
            }

            $plan->forceFill(['stripe_product_id' => $productId])->save();
        }

        $fingerprint = hash('sha256', implode('|', [
            $plan->id,
            (string) $plan->price,
            strtoupper((string) $plan->currency),
            $plan->duration_unit,
            $plan->duration_value,
        ]));

        $price = $this->request('POST', '/v1/prices', [
            'product' => $productId,
            'currency' => strtolower((string) $plan->currency),
            'unit_amount' => (int) round(((float) $plan->price) * 100),
            'recurring[interval]' => $this->stripeInterval($plan),
            'recurring[interval_count]' => max(1, (int) $plan->duration_value),
            'metadata[platform_plan_id]' => (string) $plan->id,
        ], 'muebledesk-price-'.$fingerprint);

        $priceId = (string) ($price['id'] ?? '');
        if ($priceId === '') {
            throw new RuntimeException('Stripe did not return a price ID for this plan.');
        }

        $plan->forceFill(['stripe_price_id' => $priceId])->save();
        return $priceId;
    }

    public function createBillingPortalSession(Company $company, string $returnUrl): array
    {
        $customerId = $company->subscription?->stripe_customer_id;
        if (! $customerId) {
            throw new RuntimeException('This company does not have a Stripe customer yet.');
        }

        return $this->request('POST', '/v1/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->request('GET', '/v1/checkout/sessions/'.$sessionId, ['expand[]' => 'subscription']);
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->request('GET', '/v1/subscriptions/'.$subscriptionId, [
            'expand[]' => 'latest_invoice.payment_intent',
        ]);
    }

    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        $secret = (string) config('services.stripe.platform_webhook_secret');
        if ($secret === '') {
            throw new RuntimeException('STRIPE_PLATFORM_WEBHOOK_SECRET is not configured.');
        }

        $parts = collect(explode(',', $signatureHeader))->mapWithKeys(function (string $part): array {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            return $key && $value ? [$key => $value] : [];
        });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');
        if (! $timestamp || ! $signature || abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Invalid Stripe webhook timestamp.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid Stripe webhook signature.');
        }

        return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertCompanyFitsPlan(CompanySubscription $subscription, PlatformSubscriptionPlan $targetPlan): void
    {
        $subscription->loadMissing('company');
        $company = $subscription->company;
        if (! $company) {
            throw new RuntimeException('The subscription company could not be loaded.');
        }

        $violations = collect([
            'admin' => 'admins',
            'employee' => 'employees',
            'customer' => 'clients',
        ])->map(function (string $label, string $role) use ($company, $targetPlan): ?string {
            $limit = $targetPlan->limitForRole($role);
            $used = $company->usageForRole($role);

            if ($limit !== null && $used > $limit) {
                return $used.' '.$label.' in use; target plan allows '.$limit;
            }

            return null;
        })->filter()->values();

        if ($violations->isNotEmpty()) {
            throw new RuntimeException('Reduce account usage before changing to this plan: '.$violations->implode('; ').'.');
        }
    }

    private function stripeInterval(PlatformSubscriptionPlan $plan): string
    {
        return match ($plan->duration_unit) {
            'days' => 'day',
            'years' => 'year',
            default => 'month',
        };
    }

    private function priceIdFromSubscription(array $subscription): string
    {
        $price = Arr::get($subscription, 'items.data.0.price');
        return is_array($price) ? (string) ($price['id'] ?? '') : (string) ($price ?? '');
    }

    private function stripeScheduleId(array $subscription): string
    {
        $schedule = $subscription['schedule'] ?? null;
        return is_array($schedule) ? (string) ($schedule['id'] ?? '') : (string) ($schedule ?? '');
    }

    private function mutationKey(string $action, string $subscriptionId, array $payload, array $context = []): string
    {
        $fingerprint = substr(hash('sha256', json_encode([$payload, $context], JSON_THROW_ON_ERROR)), 0, 32);
        return 'muebledesk-'.$action.'-'.$subscriptionId.'-'.$fingerprint;
    }

    private function request(string $method, string $path, array $formParams = [], ?string $idempotencyKey = null): array
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new RuntimeException('STRIPE_SECRET is not configured.');
        }

        $headers = ['Stripe-Version' => '2024-06-20'];
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = substr($idempotencyKey, 0, 255);
        }

        $options = [
            'auth' => [$secret, ''],
            'headers' => $headers,
        ];
        $options[$method === 'GET' ? 'query' : 'form_params'] = $formParams;

        $response = $this->client->request($method, 'https://api.stripe.com'.$path, $options);
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
