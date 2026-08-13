<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentGatewayService
{
    public function createPaymentLink(Invoice $invoice): ?string
    {
        try {
            if (Setting::get('auto_generate_payment_link', '1') === '0') {
                return null;
            }

            // Stripe is the only active invoice payment gateway for production.
            // Legacy HitPay support is intentionally retained below for historical
            // compatibility, but it is no longer selected for new payment links.
            return $this->createStripeCheckoutLink($invoice);
        } catch (\Throwable $e) {
            Log::error('Payment link generation failed safely', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'gateway' => 'stripe',
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function createHitPayLink(Invoice $invoice): ?string
    {
        if (Setting::get('hitpay_enabled', '1') === '0') {
            return null;
        }

        $apiKey = Setting::get('hitpay_api_key');
        if (empty($apiKey)) {
            Log::warning('HitPay API key is not configured. Cannot generate payment link for invoice '.$invoice->invoice_number);
            return null;
        }

        $amountToPay = $this->amountToPay($invoice);
        if ($amountToPay <= 0) {
            return null;
        }

        $mode = Setting::get('hitpay_mode', 'sandbox');
        $hitpayApiUrl = $mode === 'production'
            ? 'https://api.hit-pay.com/v1/payment-requests'
            : 'https://api.sandbox.hit-pay.com/v1/payment-requests';

        $reference = 'INV-'.$invoice->id.'-'.Str::random(8);
        $invoice->loadMissing('client');

        try {
            $response = Http::withHeaders([
                'X-BUSINESS-API-KEY' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Requested-With' => 'XMLHttpRequest',
            ])->asForm()->post($hitpayApiUrl, [
                'amount' => number_format($amountToPay, 2, '.', ''),
                'currency' => Setting::get('currency', 'MYR'),
                'reference_number' => $reference,
                'redirect_url' => route('payment.confirmation', ['reference' => $reference, 'gateway' => 'hitpay']),
                'webhook' => Setting::get('hitpay_webhook_url', route('hitpay.webhook')),
                'purpose' => 'Invoice '.$invoice->invoice_number,
                'email' => $invoice->client?->billing_email ?: $invoice->client?->email,
                'name' => $invoice->client?->name,
            ]);

            $responseData = $response->json();
            if ($response->successful() && isset($responseData['url'])) {
                return $responseData['url'];
            }

            Log::error('HitPay API error generating payment link', [
                'invoice_id' => $invoice->id,
                'response' => $this->redactGatewayResponse($responseData),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('HitPay payment link generation exception', [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function createStripeCheckoutLink(Invoice $invoice): ?string
    {
        if (Setting::get('stripe_enabled', '0') === '0') {
            return null;
        }

        $secretKey = Setting::get('stripe_secret_key');
        if (empty($secretKey)) {
            Log::warning('Stripe secret key is not configured. Cannot generate payment link for invoice '.$invoice->invoice_number);
            return null;
        }

        $amountToPay = $this->amountToPay($invoice);
        if ($amountToPay <= 0) {
            return null;
        }

        $invoice->loadMissing('client');
        $currency = strtolower(Setting::get('currency', 'MYR'));
        $sessionReference = 'STRIPE-'.$invoice->id.'-'.Str::random(8);
        $customerEmail = $invoice->client?->billing_email ?: $invoice->client?->email;

        try {
            $payload = [
                'mode' => 'payment',
                'success_url' => route('payment.confirmation').'?gateway=stripe&status=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.confirmation', ['gateway' => 'stripe', 'status' => 'cancelled', 'reference' => $sessionReference]),
                'client_reference_id' => $sessionReference,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $currency,
                'line_items[0][price_data][unit_amount]' => $this->amountInMinorUnits($amountToPay, $currency),
                'line_items[0][price_data][product_data][name]' => 'Invoice '.$invoice->invoice_number,
                'metadata[invoice_id]' => (string) $invoice->id,
                'metadata[invoice_number]' => (string) $invoice->invoice_number,
                'metadata[reference]' => $sessionReference,
                'payment_intent_data[metadata][invoice_id]' => (string) $invoice->id,
                'payment_intent_data[metadata][invoice_number]' => (string) $invoice->invoice_number,
                'payment_intent_data[metadata][reference]' => $sessionReference,
            ];

            if (! empty($customerEmail)) {
                $payload['customer_email'] = $customerEmail;
            }

            if (! empty($invoice->client?->name)) {
                $payload['line_items[0][price_data][product_data][description]'] = 'Payment for '.$invoice->client->name;
            }

            $response = Http::withToken($secretKey)
                ->asForm()
                ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

            $responseData = $response->json();
            if ($response->successful() && isset($responseData['url'])) {
                return $responseData['url'];
            }

            Log::error('Stripe API error generating checkout session', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'response' => $this->redactGatewayResponse($responseData),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Stripe checkout session generation exception', [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function amountToPay(Invoice $invoice): float
    {
        return max(0, (float) $invoice->total_amount - (float) $invoice->amount_paid);
    }

    private function amountInMinorUnits(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = [
            'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];

        return in_array(strtolower($currency), $zeroDecimalCurrencies, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    private function redactGatewayResponse(?array $response): ?array
    {
        if (! is_array($response)) {
            return $response;
        }

        foreach (['secret', 'client_secret', 'webhook_secret', 'api_key'] as $key) {
            if (array_key_exists($key, $response)) {
                $response[$key] = '[redacted]';
            }
        }

        return $response;
    }
}
