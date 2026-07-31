<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Setting;
use App\Models\WebhookEvent;
use App\Services\ActivityLogger;
use App\Services\DocumentNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, ActivityLogger $activityLogger)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = Setting::get('stripe_webhook_secret');

        if (empty($webhookSecret)) {
            Log::error('Stripe webhook secret is not configured.');
            return response()->json(['message' => 'Webhook configuration missing'], 500);
        }

        if (! $this->validSignature($payload, $signature, $webhookSecret)) {
            $activityLogger->log('stripe.webhook_invalid_signature', 'Stripe webhook invalid signature');
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $event = json_decode($payload, true);
        if (! is_array($event) || empty($event['type'])) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $webhookEvent = $this->rememberEvent($event);
        if ($webhookEvent->processed_at) {
            return response()->json(['message' => 'Webhook already processed'], 200);
        }

        $activityLogger->log('stripe.webhook_received', 'Stripe webhook received', null, [], [
            'type' => $event['type'],
            'id' => $event['id'] ?? null,
        ]);

        try {
            $response = match ($event['type']) {
                'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->handleCheckoutSession($event, $activityLogger),
                'payment_intent.succeeded' => $this->handlePaymentIntent($event, $activityLogger),
                default => tap(response()->json(['message' => 'Webhook ignored'], 200), fn () => $webhookEvent->markIgnored(['reason' => 'unhandled_event_type'])),
            };

            if (! $webhookEvent->processed_at && $response->getStatusCode() < 400) {
                $webhookEvent->markProcessed();
            }

            return $response;
        } catch (\Throwable $e) {
            $webhookEvent->markFailed($e);
            throw $e;
        }
    }

    private function handleCheckoutSession(array $event, ActivityLogger $activityLogger)
    {
        $session = $event['data']['object'] ?? [];

        if (($session['payment_status'] ?? null) !== 'paid') {
            return response()->json(['message' => 'Checkout session not paid'], 200);
        }

        $invoiceId = $session['metadata']['invoice_id'] ?? null;
        $transactionId = $session['payment_intent'] ?? $session['id'] ?? null;
        $reference = $session['client_reference_id'] ?? $session['metadata']['reference'] ?? $session['id'] ?? null;
        $amount = $this->amountFromMinorUnits((int) ($session['amount_total'] ?? 0), (string) ($session['currency'] ?? Setting::get('currency', 'MYR')));

        return $this->recordStripePayment($invoiceId, $transactionId, $reference, $amount, $activityLogger);
    }

    private function handlePaymentIntent(array $event, ActivityLogger $activityLogger)
    {
        $intent = $event['data']['object'] ?? [];
        $invoiceId = $intent['metadata']['invoice_id'] ?? null;
        $transactionId = $intent['id'] ?? null;
        $reference = $intent['metadata']['reference'] ?? $transactionId;
        $amount = $this->amountFromMinorUnits((int) ($intent['amount_received'] ?? $intent['amount'] ?? 0), (string) ($intent['currency'] ?? Setting::get('currency', 'MYR')));

        return $this->recordStripePayment($invoiceId, $transactionId, $reference, $amount, $activityLogger);
    }

    private function recordStripePayment(?string $invoiceId, ?string $transactionId, ?string $reference, float $amount, ActivityLogger $activityLogger)
    {
        if (! $invoiceId || ! $transactionId) {
            return response()->json(['message' => 'Missing invoice or transaction reference'], 400);
        }

        DB::transaction(function () use ($invoiceId, $transactionId, $reference, $amount, $activityLogger) {
            /** @var Invoice|null $invoice */
            $invoice = Invoice::query()->whereKey($invoiceId)->lockForUpdate()->first();

            if (! $invoice) {
                throw new \RuntimeException('Invoice not found for Stripe webhook: '.$invoiceId);
            }

            $outstanding = max(0, (float) $invoice->total_amount - (float) $invoice->amount_paid);
            $paymentAmount = min($amount > 0 ? $amount : $outstanding, $outstanding);

            if ($paymentAmount <= 0) {
                return;
            }

            $payment = Payment::firstOrCreate(
                ['transaction_id' => $transactionId],
                [
                    'invoice_id' => $invoice->id,
                    'amount' => $paymentAmount,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => 'stripe',
                    'transaction_reference' => $reference,
                    'notes' => 'Automatically recorded by Stripe webhook',
                ]
            );

            if (! $payment->wasRecentlyCreated) {
                return;
            }

            PaymentReceipt::firstOrCreate(
                ['payment_id' => $payment->id],
                [
                    'receipt_number' => app(DocumentNumberGenerator::class)->generate(
                        new PaymentReceipt,
                        'receipt_number',
                        'receipt_prefix',
                        'receipt_number_format',
                        'REC',
                        $payment->payment_date,
                        (int) ($invoice->employee_id ?? 0),
                        'receipt_number'
                    ),
                    'date' => $payment->payment_date,
                    'amount' => $payment->amount,
                ]
            );

            $invoice->amount_paid = min((float) $invoice->total_amount, (float) $invoice->amount_paid + (float) $payment->amount);
            $invoice->status = $invoice->amount_paid >= $invoice->total_amount
                ? 'paid'
                : ($invoice->amount_paid > 0 ? 'partially_paid' : 'pending');
            $invoice->locked_at ??= now();
            $invoice->payment_link = $invoice->status === 'paid' ? null : $invoice->payment_link;
            $invoice->save();

            $activityLogger->log('payment.recorded', 'Stripe payment recorded for invoice '.$invoice->invoice_number, $payment, [], $payment->toArray());
        });

        return response()->json(['message' => 'Stripe payment processed'], 200);
    }

    private function rememberEvent(array $event): WebhookEvent
    {
        $object = $event['data']['object'] ?? [];
        $eventId = (string) ($event['id'] ?? hash('sha256', json_encode($event)));
        $transactionId = $object['payment_intent'] ?? $object['id'] ?? null;

        return WebhookEvent::firstOrCreate(
            ['gateway' => 'stripe', 'event_id' => $eventId],
            [
                'event_type' => $event['type'] ?? null,
                'transaction_id' => $transactionId,
                'status' => 'received',
                'payload_summary' => [
                    'invoice_id' => $object['metadata']['invoice_id'] ?? null,
                    'payment_status' => $object['payment_status'] ?? null,
                    'currency' => $object['currency'] ?? null,
                ],
                'received_at' => now(),
            ]
        );
    }

    private function validSignature(string $payload, ?string $signatureHeader, string $secret): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (! $timestamp || ! $signature) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function amountFromMinorUnits(int $amount, string $currency): float
    {
        $zeroDecimalCurrencies = [
            'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];

        return in_array(strtolower($currency), $zeroDecimalCurrencies, true)
            ? (float) $amount
            : round($amount / 100, 2);
    }
}
