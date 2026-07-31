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

class HitPayWebhookController extends Controller
{
    public function handle(Request $request, ActivityLogger $activityLogger)
    {
        $activityLogger->log('hitpay.webhook_received', 'HitPay webhook received', null, [], ['status' => $request->input('status'), 'id' => $request->input('id')]);
        Log::info('HitPay Webhook received.', [
            'payload' => $this->safePayload($request->all()),
            'headers' => ['hitpay-signature' => $request->header('hitpay-signature') ? '[present]' : null],
        ]);

        $webhookSalt = Setting::get('hitpay_webhook_salt');

        if (empty($webhookSalt)) {
            Log::error('HitPay Webhook: Webhook Salt Key is not configured in settings. Cannot verify webhook.');
            return response()->json(['message' => 'Webhook processing error: Configuration missing'], 500);
        }

        $hitpaySignature = $request->header('hitpay-signature') ?? $request->header('X-Signature');
        $rawRequestBody = $request->getContent();
        $generatedSignature = hash_hmac('sha256', $rawRequestBody, $webhookSalt);

        if (! $hitpaySignature || ! hash_equals($generatedSignature, $hitpaySignature)) {
            Log::warning('HitPay Webhook: Invalid signature. Request might be fraudulent.', [
                'received_signature' => $hitpaySignature ? '[present]' : null,
                'payload' => $this->safePayload($request->all()),
            ]);
            return response()->json(['message' => 'Invalid webhook signature'], 403);
        }

        $payload = $request->json()->all();
        if (empty($payload)) {
            $payload = $request->all();
        }

        $webhookEvent = $this->rememberEvent($payload);
        if ($webhookEvent->processed_at) {
            return response()->json(['message' => 'Webhook already processed'], 200);
        }

        try {
            $response = $this->processPayload($payload, $activityLogger);

            if ($response->getStatusCode() < 400) {
                $webhookEvent->markProcessed();
            }

            return $response;
        } catch (\Throwable $e) {
            $webhookEvent->markFailed($e);
            throw $e;
        }
    }

    private function processPayload(array $payload, ActivityLogger $activityLogger)
    {
        $referenceNumber = $payload['payment_request']['reference_number'] ?? $payload['remark'] ?? null;

        if (! $referenceNumber) {
            Log::error('HitPay Webhook: No reference number or remark found in payload.');
            return response()->json(['message' => 'Invalid payload: no reference number'], 400);
        }

        $parts = explode('-', $referenceNumber);
        $invoiceId = $parts[1] ?? null;

        if (! $invoiceId) {
            Log::error('HitPay Webhook: Could not extract invoice ID from reference_number: '.$referenceNumber);
            return response()->json(['message' => 'Invalid reference number format'], 400);
        }

        $status = $payload['status'] ?? null;

        switch ($status) {
            case 'succeeded':
            case 'completed':
                DB::transaction(function () use ($invoiceId, $payload, $referenceNumber, $activityLogger) {
                    /** @var Invoice|null $invoice */
                    $invoice = Invoice::query()->whereKey($invoiceId)->lockForUpdate()->first();

                    if (! $invoice) {
                        throw new \RuntimeException('HitPay webhook invoice not found: '.$invoiceId);
                    }

                    $outstanding = max(0, (float) $invoice->total_amount - (float) $invoice->amount_paid);
                    if ($outstanding <= 0) {
                        return;
                    }

                    $payment = Payment::firstOrCreate(
                        ['transaction_id' => $payload['id'] ?? 'hitpay-'.$invoice->id],
                        [
                            'invoice_id' => $invoice->id,
                            'amount' => $outstanding,
                            'payment_date' => now()->toDateString(),
                            'payment_method' => 'hitpay',
                            'transaction_reference' => $referenceNumber,
                            'notes' => 'Automatically recorded by HitPay webhook',
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
                    $invoice->hitpay_payment_id = $payload['id'] ?? null;
                    $invoice->locked_at ??= now();
                    $invoice->payment_link = $invoice->status === 'paid' ? null : $invoice->payment_link;
                    $invoice->save();

                    $activityLogger->log('payment.recorded', 'HitPay payment recorded for invoice '.$invoice->invoice_number, $payment, [], $payment->toArray());
                });
                break;

            case 'failed':
                DB::transaction(function () use ($invoiceId) {
                    $invoice = Invoice::query()->whereKey($invoiceId)->lockForUpdate()->first();
                    if ($invoice && $invoice->status === 'pending') {
                        $invoice->status = 'failed_payment';
                        $invoice->save();
                    }
                });
                break;

            case 'pending':
                Log::info('HitPay Webhook: Payment for invoice '.$invoiceId.' is still pending.');
                break;

            default:
                Log::info('HitPay Webhook: Unknown status "'.$status.'" received for invoice '.$invoiceId);
                break;
        }

        return response()->json(['message' => 'Webhook received and processed'], 200);
    }

    private function rememberEvent(array $payload): WebhookEvent
    {
        $reference = $payload['payment_request']['reference_number'] ?? $payload['remark'] ?? null;
        $eventId = (string) ($payload['id'] ?? hash('sha256', json_encode([$reference, $payload['status'] ?? null, $payload['amount'] ?? null])));

        return WebhookEvent::firstOrCreate(
            ['gateway' => 'hitpay', 'event_id' => $eventId],
            [
                'event_type' => $payload['status'] ?? null,
                'transaction_id' => $payload['id'] ?? null,
                'status' => 'received',
                'payload_summary' => [
                    'reference_number' => $reference,
                    'status' => $payload['status'] ?? null,
                    'amount' => $payload['amount'] ?? null,
                ],
                'received_at' => now(),
            ]
        );
    }

    private function safePayload(array $payload): array
    {
        return collect($payload)->except(['signature', 'salt', 'api_key', 'secret'])->all();
    }
}
