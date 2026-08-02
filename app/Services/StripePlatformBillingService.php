<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PlatformSubscriptionPlan;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use RuntimeException;

class StripePlatformBillingService
{
    public function __construct(private readonly Client $client)
    {
    }

    public function createCheckoutSession(Company $company, PlatformSubscriptionPlan $plan, int $seats, string $successUrl, string $cancelUrl): array
    {
        if (! $plan->stripe_price_id) {
            throw new RuntimeException('The selected plan does not have a Stripe price ID.');
        }

        $subscription = $company->subscription()->firstOrCreate([], [
            'platform_subscription_plan_id' => $plan->id,
            'seats' => $seats,
            'status' => 'incomplete',
        ]);

        $payload = [
            'mode' => 'subscription',
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $company->id,
            'customer_email' => $company->email ?: $company->owners()->value('email'),
            'line_items[0][price]' => $plan->stripe_price_id,
            'line_items[0][quantity]' => $seats,
            'metadata[company_id]' => (string) $company->id,
            'metadata[plan_id]' => (string) $plan->id,
            'metadata[seats]' => (string) $seats,
            'subscription_data[metadata][company_id]' => (string) $company->id,
            'subscription_data[metadata][plan_id]' => (string) $plan->id,
            'subscription_data[metadata][seats]' => (string) $seats,
        ];

        if ($plan->trial_days > 0) {
            $payload['subscription_data[trial_period_days]'] = $plan->trial_days;
        }

        $session = $this->request('POST', '/v1/checkout/sessions', $payload);

        $subscription->update([
            'platform_subscription_plan_id' => $plan->id,
            'seats' => $seats,
            'stripe_checkout_session_id' => Arr::get($session, 'id'),
            'status' => 'incomplete',
        ]);

        return $session;
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
        return $this->request('GET', '/v1/checkout/sessions/'.$sessionId, [
            'expand[]' => 'subscription',
        ]);
    }

    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        $secret = (string) config('services.stripe.platform_webhook_secret');

        if ($secret === '') {
            throw new RuntimeException('STRIPE_PLATFORM_WEBHOOK_SECRET is not configured.');
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part): array {
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

        $options = [
            'auth' => [$secret, ''],
            'headers' => ['Stripe-Version' => '2024-06-20'],
        ];

        if ($method === 'GET') {
            $options['query'] = $formParams;
        } else {
            $options['form_params'] = $formParams;
        }

        $response = $this->client->request($method, 'https://api.stripe.com'.$path, $options);

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
