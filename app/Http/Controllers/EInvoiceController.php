<?php

namespace App\Http\Controllers;

use App\Jobs\PollEInvoiceStatus;
use App\Models\EInvoice;
use App\Models\EInvoiceSubmission;
use App\Models\Invoice;
use App\Services\MyInvois\InvoiceReadiness;
use App\Services\MyInvois\MyInvoisClient;
use App\Services\MyInvois\UblInvoiceBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class EInvoiceController extends Controller
{
    private const RETRYABLE_STATUSES = ['invalid', 'rejected', 'failed'];

    public function preview(Invoice $invoice, InvoiceReadiness $readiness, UblInvoiceBuilder $builder): View
    {
        $this->authorizeInvoice($invoice);
        $invoice->load('client', 'items', 'eInvoice.submission');
        $check = $readiness->check($invoice);
        $eInvoice = $invoice->eInvoice;
        $canRetry = $eInvoice && (
            in_array($eInvoice->status, self::RETRYABLE_STATUSES, true)
            || $this->isSafeLocalPreflightRecord($eInvoice)
        ) && (! $eInvoice->retry_after_at || $eInvoice->retry_after_at->isPast());
        $payload = $check['ready'] && (! $eInvoice || $canRetry) ? $builder->build($invoice) : null;

        return view('einvoices.preview', compact('invoice', 'check', 'payload', 'eInvoice', 'canRetry'));
    }

    public function submit(Request $request, Invoice $invoice, InvoiceReadiness $readiness, UblInvoiceBuilder $builder, MyInvoisClient $client): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        $invoice->loadMissing('client', 'items', 'eInvoice.submission');
        $environment = $client->environment();
        $customerView = Auth::user()?->isCustomer() ?? false;

        if ($environment === 'production' && ! $customerView) {
            $request->validate([
                'confirm_live_submission' => ['accepted'],
            ], [
                'confirm_live_submission.accepted' => 'Confirm that the invoice details are correct before live submission.',
            ]);
        }

        $existing = $invoice->eInvoice;
        $safeLocalRetry = $existing && $this->isSafeLocalPreflightRecord($existing);

        if ($existing && ! $safeLocalRetry && ! in_array($existing->status, self::RETRYABLE_STATUSES, true)) {
            return back()->with('error', 'An active or completed e-Invoice already exists for this invoice.');
        }
        if ($existing?->retry_after_at?->isFuture() && ! $safeLocalRetry) {
            return back()->with('error', 'MyInvois requested a retry delay. Try again after '.$existing->retry_after_at->format('Y-m-d H:i:s').'.');
        }
        if (! config('myinvois.enabled')) return back()->with('error', 'MyInvois submission is currently unavailable.');

        $check = $readiness->check($invoice);
        if (! $check['ready']) return back()->with('error', 'The invoice details are incomplete. Please review them before submission.');

        try {
            if (! $client->validateTin((string) $invoice->client->tin_number, strtoupper((string) $invoice->client->id_type), (string) $invoice->client->id_number)) {
                return back()->with('error', 'Buyer TIN does not match the configured ID type and ID number.');
            }
        } catch (Throwable $exception) {
            Log::error('MyInvois buyer TIN validation failed.', ['invoice_id' => $invoice->id, 'message' => $exception->getMessage()]);
            return back()->with('error', $customerView
                ? 'We could not verify your e-Invoice details right now. Please try again shortly.'
                : 'Buyer TIN validation could not be completed: '.$exception->getMessage());
        }

        $built = $builder->build($invoice);

        try {
            $api = $client->submitDocuments([$built['submission_document']]);
            $body = $api['body'];

            if (! $api['successful']) {
                $retryAt = $api['retry_after'] > 0 ? now()->addSeconds($api['retry_after']) : null;
                $errorCode = data_get($body, 'error.code') ?? data_get($body, 'code');
                $status = match ($api['status']) {
                    429 => 'failed',
                    422 => 'reconciliation_required',
                    default => $api['status'] >= 500 ? 'reconciliation_required' : 'rejected',
                };

                EInvoice::updateOrCreate(
                    ['invoice_id' => $invoice->id, 'environment' => $environment],
                    [
                        'document_type' => 'invoice', 'document_version' => config('myinvois.document_version', '1.0'),
                        'internal_document_number' => $invoice->invoice_number, 'status' => $status,
                        'document_hash' => $built['hash'], 'failure_reason' => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'correlation_id' => $api['correlation_id'], 'retry_after_at' => $retryAt,
                        'submission_attempts' => ($existing?->submission_attempts ?? 0) + 1, 'request_payload' => $built['document'],
                        'response_payload' => $body, 'created_by' => Auth::id(),
                    ]
                );

                $message = $api['status'] === 429
                    ? 'MyInvois is busy. Please retry after '.($api['retry_after'] ?: 'the advised delay').' seconds.'
                    : ($api['status'] === 422 || $errorCode === 'DuplicateSubmission'
                        ? 'The submission requires staff review before it can be retried.'
                        : 'MyInvois rejected the invoice. Please review the details and try again.');
                return back()->with('error', $message);
            }

            $eInvoice = DB::transaction(function () use ($invoice, $built, $body, $existing, $environment, $api) {
                $accepted = collect($body['acceptedDocuments'] ?? [])->firstWhere('invoiceCodeNumber', $invoice->invoice_number)
                    ?? collect($body['acceptedDocuments'] ?? [])->first();
                $rejected = collect($body['rejectedDocuments'] ?? [])->firstWhere('invoiceCodeNumber', $invoice->invoice_number)
                    ?? collect($body['rejectedDocuments'] ?? [])->first();
                $submissionUid = $body['submissionUID'] ?? $body['submissionUid'] ?? null;

                $submission = EInvoiceSubmission::create([
                    'environment' => $environment, 'submission_uid' => $submissionUid,
                    'status' => $accepted ? 'submitted' : 'rejected', 'document_count' => 1,
                    'submitted_at' => now(), 'request_payload' => ['documents' => [$built['submission_document']]],
                    'response_payload' => $body,
                    'failure_reason' => $rejected ? json_encode($rejected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'created_by' => Auth::id(),
                ]);

                $values = [
                    'einvoice_submission_id' => $submission->id, 'environment' => $environment,
                    'document_type' => 'invoice', 'document_version' => config('myinvois.document_version', '1.0'),
                    'internal_document_number' => $invoice->invoice_number, 'status' => $accepted ? 'submitted' : 'rejected',
                    'myinvois_uuid' => $accepted['uuid'] ?? null, 'long_id' => null, 'document_hash' => $built['hash'],
                    'submitted_at' => now(), 'validated_at' => null,
                    'failure_reason' => $rejected ? json_encode($rejected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'validation_errors' => isset($rejected['error']) ? (array) $rejected['error'] : null,
                    'request_payload' => $built['document'], 'response_payload' => $body,
                    'correlation_id' => $api['correlation_id'], 'retry_after_at' => null,
                    'submission_attempts' => ($existing?->submission_attempts ?? 0) + 1, 'created_by' => Auth::id(),
                ];

                if ($existing) { $existing->update($values); return $existing->fresh(); }
                return EInvoice::create(array_merge(['invoice_id' => $invoice->id], $values));
            });

            if ($eInvoice->status === 'submitted') PollEInvoiceStatus::dispatch($eInvoice->id)->delay(now()->addSeconds(5));

            return redirect()->route($this->previewRoute(), $invoice)->with(
                $eInvoice->status === 'submitted' ? 'success' : 'error',
                $eInvoice->status === 'submitted' ? 'Your e-Invoice was submitted successfully. Status checking is automatic.' : 'MyInvois rejected the document. Please review the details and retry.'
            );
        } catch (Throwable $exception) {
            Log::error('MyInvois submission failed.', ['invoice_id' => $invoice->id, 'message' => $exception->getMessage()]);

            if ($this->isLocalPreflightFailure($exception)) {
                return back()->with('error', $customerView
                    ? 'e-Invoice submission is temporarily unavailable. Please contact support.'
                    : $exception->getMessage());
            }

            EInvoice::updateOrCreate(
                ['invoice_id' => $invoice->id, 'environment' => $environment],
                [
                    'document_type' => 'invoice', 'document_version' => config('myinvois.document_version', '1.0'),
                    'internal_document_number' => $invoice->invoice_number, 'status' => 'reconciliation_required',
                    'document_hash' => $built['hash'], 'failure_reason' => $exception->getMessage(),
                    'retry_after_at' => now()->addMinutes(10), 'request_payload' => $built['document'],
                    'submission_attempts' => max(1, (int) ($existing?->submission_attempts ?? 0)),
                    'created_by' => Auth::id(),
                ]
            );
            return back()->with('error', $customerView
                ? 'We could not confirm the submission result. Our team needs to review it before another attempt.'
                : 'The submission result is uncertain. Automatic retry is blocked to prevent a duplicate tax document.');
        }
    }

    public function refresh(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        $eInvoice = $invoice->eInvoice;
        if (! $eInvoice?->submission?->submission_uid) return back()->with('error', 'No MyInvois submission UID exists for this invoice.');
        PollEInvoiceStatus::dispatchSync($eInvoice->id);
        return back()->with('success', 'MyInvois status refreshed: '.strtoupper($eInvoice->fresh()->status).'.');
    }

    public function cancel(Request $request, Invoice $invoice, MyInvoisClient $client): RedirectResponse
    {
        $this->authorizeStaffInvoice($invoice);
        $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $eInvoice = $invoice->eInvoice;
        if (! $eInvoice?->canCancel()) return back()->with('error', 'This e-Invoice is not eligible for cancellation or the cancellation window has expired.');

        $api = $client->cancelDocument($eInvoice->myinvois_uuid, $request->string('reason')->toString());
        if (! $api['successful']) {
            return back()->with('error', data_get($api, 'body.error.message') ?: 'MyInvois rejected the cancellation request.');
        }

        $eInvoice->update([
            'status' => 'cancelled', 'cancelled_at' => now(),
            'cancellation_reason' => $request->string('reason')->toString(), 'cancelled_by' => Auth::id(),
            'response_payload' => array_merge($eInvoice->response_payload ?? [], ['cancellation' => $api['body']]),
            'correlation_id' => $api['correlation_id'] ?: $eInvoice->correlation_id,
        ]);
        $invoice->update(['locked_at' => null]);
        return back()->with('success', 'The e-Invoice was cancelled successfully.');
    }

    private function isSafeLocalPreflightRecord(EInvoice $eInvoice): bool
    {
        return $eInvoice->status === 'reconciliation_required'
            && (int) $eInvoice->submission_attempts === 0
            && blank($eInvoice->myinvois_uuid)
            && blank($eInvoice->submission?->submission_uid);
    }

    private function isLocalPreflightFailure(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Production MyInvois submissions require APP_ENV=production')
            || str_contains($message, 'Production submission is disabled')
            || str_contains($message, 'MyInvois submission is disabled')
            || str_contains($message, 'MyInvois credentials are missing')
            || str_contains($message, 'Invalid MyInvois environment configured');
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        $user = Auth::user();
        abort_unless($user, 403);
        if ($user->isCustomer()) {
            abort_unless((int) $invoice->client?->user_id === (int) $user->id, 403);
            return;
        }
        abort_if($user->isEmployee() && (int) $invoice->employee_id !== (int) $user->id, 403);
    }

    private function authorizeStaffInvoice(Invoice $invoice): void
    {
        abort_if(Auth::user()?->isCustomer(), 403);
        $this->authorizeInvoice($invoice);
    }

    private function previewRoute(): string
    {
        return Auth::user()?->isCustomer() ? 'customer.einvoices.preview' : 'einvoices.preview';
    }
}
