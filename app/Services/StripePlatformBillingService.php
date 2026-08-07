<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use DateTimeInterface;
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

        $interval = match ($plan->duration_unit) {
            'days' => 'day',
            'years' => 'year',
            default => 'month',
        };

        $payload = [
            'mode' => 'subscription',
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $company->id,
            'customer_email' => $company->email ?: $company->owners()->value('email'),
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($plan->currency),
            'line_items[0][price_data][unit_amount]' => (int) round(((float) $plan->price) * 100),
            'line_items[0][price_data][product_data][name]' => $plan->name,
            'line_items[0][price_data][product_data][description]' => $plan->description ?: 'MuebleDesk subscription',
            'line_items[0][price_data][recurring][interval]' => $interval,
            'line_items[0][price_data][recurring][interval_count]' => $plan->duration_value,
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
            'stripe_checkout_session_id' => Arr::get($session, 'id'),
            'status' => 'incomplete',
            'auto_renew' => $autoRenew,
            'is_enabled' => true,
        ]);

        return $session;
    }

    public function createExtensionCheckoutSession(Company $company, CompanySubscription $subscription, PlatformSubscriptionPlan $plan, string $successUrl, string $cancelUrl): array
    {
        if (! $subscription->isActive() || (int) $subscription->platform_subscription_plan_id !== (int) $plan->id) {
            throw new RuntimeException('Only the currently active plan can be extended.');
        }

        $payload = [
            'mode' => 'payment',
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $company->id,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($plan->currency),
            'line_items[0][price_data][unit_amount]' => (int) round(((float) $plan->price) * 100),
            'line_items[0][price_data][product_data][name]' => $plan->name.' extension',
            'line_items[0][price_data][product_data][description]' => 'Prepaid extension for '.$plan->durationLabel(),
            'metadata[company_id]' => (string) $company->id,
            'metadata[plan_id]' => (string) $plan->id,
            'metadata[purchase_type]' => 'extension',
            'metadata[existing_subscription_id]' => (string) $subscription->id,
        ];

        if ($subscription->stripe_customer_id) {
            $payload['customer'] = $subscription->stripe_customer_id;
        } else {
            $payload['customer_email'] = $company->email ?: $company->owners()->value('email');
        }

        return $this->request('POST', '/v1/checkout/sessions', $payload);
    }

    public function postponeRenewalTo(CompanySubscription $subscription, DateTimeInterface $renewAt): array
    {
        if (! $subscription->stripe_subscription_id) {
            throw new RuntimeException('This subscription does not have a Stripe subscription ID.');
        }

        return $this->request('POST', '/v1/subscriptions/'.$subscription->stripe_subscription_id, [
            'trial_end' => $renewAt->getTimestamp(),
            'proration_behavior' => 'none',
            'cancel_at_period_end' => 'false',
        ]);
    }

    public function setAutoRenew(CompanySubscription $subscription, bool $enabled): void
    {
        if ($subscription->stripe_subscription_id) {
            $this->request('POST', '/v1/subscriptions/'.$subscription->stripe_subscription_id, [
                'cancel_at_period_end' => $enabled ? 'false' : 'true',
            ]);
        }

        $subscription->update(['auto_renew' => $enabled]);
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

    private function request(string $method, string $path, array $formParams = []): array
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new RuntimeException('STRIPE_SECRET is not configured.');
        }

        $options = ['auth' => [$secret, ''], 'headers' => ['Stripe-Version' => '2024-06-20']];
        $options[$method === 'GET' ? 'query' : 'form_params'] = $formParams;
        $response = $this->client->request($method, 'https://api.stripe.com'.$path, $options);

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
