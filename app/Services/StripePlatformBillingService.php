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
            'customer_email' => $company->email ?: $company->owners()->value('email'),
            'line_items[0][quantity]' => 1,
            'line_items[0][price]' => $priceId,
            'metadata[company_id]' => (string) $company->id,
            'metadata[plan_id]' => (string) $plan->id,
            'metadata[auto_renew]' => $autoRenew ? '1' : '0',
            'metadata[purchase_type]' => 'subscription',
            'subscription_data[metadata][company_id]' => (string) $company->id,
            'subscription_data[metadata][plan_id]' => (string) $plan->id,
        ];

        if (! $autoRenew) {
            $payload['subscription_data[cancel_at_period_end]'] = 'true';
        }

        $session = $this->request('POST', '/v1/checkout/sessions', $payload);

        $subscription->update([
            'platform_subscription_plan_id' => $plan->id,
            'pending_platform_subscription_plan_id' => null,
            'pending_plan_effective_at' => null,
            'stripe_subscription_schedule_id' => null,
            'stripe_checkout_session_id' => Arr::get($session, 'id'),
            'status' => 'incomplete',
            'auto_renew' => $autoRenew,
            'is_enabled' => true,
        ]);

        return $session;
    }

    /**
     * Upgrade an existing subscription immediately. Stripe creates and attempts
     * the prorated invoice now. pending_if_incomplete means the higher-priced
     * subscription item is not applied unless that invoice can be paid.
     */
    public function upgradeSubscription(CompanySubscription $subscription, PlatformSubscriptionPlan $targetPlan): array
    {
        if (! $subscription->stripe_subscription_id) {
            throw new RuntimeException('The current subscription is missing its Stripe subscription ID.');
        }

        $remote = $this->retrieveSubscription($subscription->stripe_subscription_id);
        if (! in_array((string) ($remote['status'] ?? ''), ['active', 'trialing'], true)) {
            throw new RuntimeException('Only an active Stripe subscription can be upgraded.');
        }

        if (($remote['collection_method'] ?? 'charge_automatically') !== 'charge_automatically') {
            throw new RuntimeException('Immediate upgrades require automatic Stripe payment collection.');
        }

        if (! empty($remote['pending_update'])) {
            throw new RuntimeException('A Stripe subscription update is already awaiting payment. Resolve it before changing plans again.');
        }

        if ($this->stripeScheduleId($remote) !== '') {
            throw new RuntimeException('A future Stripe subscription change is already scheduled. Cancel that scheduled change before upgrading.');
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
        $idempotencyKey = 'muebledesk-upgrade-'.$subscription->stripe_subscription_id.'-'.$targetPlan->id.'-'.($remote['current_period_end'] ?? 'period');

        $updated = $this->request('POST', '/v1/subscriptions/'.$subscription->stripe_subscription_id, [
            'payment_behavior' => 'pending_if_incomplete',
            'proration_behavior' => 'always_invoice',
            'items[0][id]' => (string) $item['id'],
            'items[0][price]' => $targetPriceId,
            'expand[0]' => 'latest_invoice.payment_intent',
        ], $idempotencyKey);

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

        $appliedPriceId = $this->priceIdFromSubscription($updated);
        if ($appliedPriceId !== $targetPriceId) {
            throw new RuntimeException('Stripe did not confirm the requested upgraded price. Local entitlements were not changed.');
        }

        // Metadata is non-billing state. Update it only after the paid price
        // change has been applied so webhook reconciliation can identify the plan.
        $updated = $this->request('POST', '/v1/subscriptions/'.$subscription->stripe_subscription_id, [
            'metadata[company_id]' => (string) $subscription->company_id,
            'metadata[plan_id]' => (string) $targetPlan->id,
        ], $idempotencyKey.'-metadata');

        return [
            'applied' => true,
            'subscription' => $updated,
            'invoice_url' => null,
            'payment_intent_status' => null,
        ];
    }

    /**
     * Schedule a downgrade (or a billing-interval change) for the next renewal.
     * The customer keeps the plan already paid for, and Stripe changes the price
     * only when the current period ends.
     */
    public function schedulePlanChangeAtPeriodEnd(CompanySubscription $subscription, PlatformSubscriptionPlan $targetPlan): array
    {
        if (! $subscription->stripe_subscription_id) {
            throw new RuntimeException('The current subscription is missing its Stripe subscription ID.');
        }

        $remote = $this->retrieveSubscription($subscription->stripe_subscription_id);
        if (! in_array((string) ($remote['status'] ?? ''), ['active', 'trialing'], true)) {
            throw new RuntimeException('Only an active Stripe subscription can schedule a future plan change.');
        }

        if ((bool) ($remote['cancel_at_period_end'] ?? false)) {
            throw new RuntimeException('Auto-renewal is disabled. Re-enable renewal before scheduling a plan for the next billing period.');
        }

        if (! empty($remote['pending_update'])) {
            throw new RuntimeException('A Stripe subscription update is already awaiting payment. Resolve it before scheduling another change.');
        }

        $item = Arr::get($remote, 'items.data.0');
        if (! is_array($item) || empty($item['id'])) {
            throw new RuntimeException('Stripe did not return the subscription item required for this plan change.');
        }

        $currentPriceId = $this->priceIdFromSubscription($remote);
        if ($currentPriceId === '') {
            throw new RuntimeException('Stripe did not return the current subscription price.');
        }

        $currentCurrency = strtolower((string) Arr::get($item, 'price.currency', ''));
        if ($currentCurrency !== '' && $currentCurrency !== strtolower((string) $targetPlan->currency)) {
            throw new RuntimeException('Scheduled plan changes cannot switch subscription currency.');
        }

        $currentStart = (int) ($remote['current_period_start'] ?? 0);
        $currentEnd = (int) ($remote['current_period_end'] ?? 0);
        if ($currentStart <= 0 || $currentEnd <= $currentStart || $currentEnd <= time()) {
            throw new RuntimeException('Stripe did not return a valid future billing period for the plan change.');
        }

        $targetPriceId = $this->ensurePlanPrice($targetPlan);
        $scheduleId = $this->stripeScheduleId($remote);

        if ($scheduleId !== '' && $subscription->stripe_subscription_schedule_id !== $scheduleId) {
            throw new RuntimeException('This Stripe subscription already has an external schedule. Resolve that schedule before changing plans here.');
        }

        if ($scheduleId === '') {
            $schedule = $this->request('POST', '/v1/subscription_schedules', [
                'from_subscription' => $subscription->stripe_subscription_id,
            ], 'muebledesk-schedule-create-'.$subscription->stripe_subscription_id.'-'.$currentEnd);
            $scheduleId = (string) ($schedule['id'] ?? '');

            if ($scheduleId === '') {
                throw new RuntimeException('Stripe did not return a subscription schedule ID.');
            }
        }

        $targetInterval = $this->stripeInterval($targetPlan);
        $schedule = $this->request('POST', '/v1/subscription_schedules/'.$scheduleId, [
            'end_behavior' => 'release',
            'proration_behavior' => 'none',
            'phases[0][start_date]' => $currentStart,
            'phases[0][end_date]' => $currentEnd,
            'phases[0][items][0][price]' => $currentPriceId,
            'phases[0][items][0][quantity]' => max(1, (int) ($item['quantity'] ?? 1)),
            'phases[0][proration_behavior]' => 'none',
            'phases[1][start_date]' => $currentEnd,
            'phases[1][items][0][price]' => $targetPriceId,
            'phases[1][items][0][quantity]' => max(1, (int) ($item['quantity'] ?? 1)),
            'phases[1][duration][interval]' => $targetInterval,
            'phases[1][duration][interval_count]' => max(1, (int) $targetPlan->duration_value),
            'phases[1][proration_behavior]' => 'none',
        ], 'muebledesk-schedule-change-'.$scheduleId.'-'.$targetPlan->id.'-'.$currentEnd);

        return [
            'schedule' => $schedule,
            'schedule_id' => $scheduleId,
            'effective_at' => $currentEnd,
        ];
    }

    public function cancelScheduledPlanChange(CompanySubscription $subscription): void
    {
        $scheduleId = (string) $subscription->stripe_subscription_schedule_id;
        if ($scheduleId === '') {
            return;
        }

        $this->request(
            'POST',
            '/v1/subscription_schedules/'.$scheduleId.'/release',
            [],
            'muebledesk-schedule-release-'.$scheduleId
        );
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

        return is_array($price)
            ? (string) ($price['id'] ?? '')
            : (string) ($price ?? '');
    }

    private function stripeScheduleId(array $subscription): string
    {
        $schedule = $subscription['schedule'] ?? null;

        return is_array($schedule)
            ? (string) ($schedule['id'] ?? '')
            : (string) ($schedule ?? '');
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
