<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\EInvoice;
use App\Models\Setting;
use App\Notifications\EInvoiceStatusNotification;
use App\Services\MyInvois\MyInvoisClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollEInvoiceStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;
    public array $backoff = [5, 10, 20, 30, 60, 120, 300];

    public function __construct(public readonly int $eInvoiceId)
    {
    }

    public function handle(MyInvoisClient $client): void
    {
        $eInvoice = EInvoice::withoutGlobalScopes()->with(['invoice.client.user', 'submission'])->find($this->eInvoiceId);
        if (! $eInvoice || in_array($eInvoice->status, ['valid', 'invalid', 'cancelled'], true)) return;

        $company = Company::query()->find($eInvoice->company_id);
        if (! $company) {
            Log::error('Automated MyInvois polling could not resolve tenant company.', ['einvoice_id' => $this->eInvoiceId, 'company_id' => $eInvoice->company_id]);
            return;
        }

        app()->instance(Company::class, $company);
        app()->instance('currentCompany', $company);
        config([
            'myinvois.enabled' => filter_var(Setting::get('myinvois_enabled', '0'), FILTER_VALIDATE_BOOL),
            'myinvois.environment' => (string) Setting::get('myinvois_environment', $eInvoice->environment ?: 'sandbox'),
        ]);

        $uid = $eInvoice->submission?->submission_uid;
        if (! $uid) return;

        $response = $client->getSubmission($uid);
        $summary = collect($response['documentSummary'] ?? [])->firstWhere('internalId', $eInvoice->internal_document_number)
            ?? collect($response['documentSummary'] ?? [])->first();
        $status = strtolower((string) ($summary['status'] ?? $response['overallStatus'] ?? 'submitted'));
        $status = match ($status) {
            'valid' => 'valid', 'invalid' => 'invalid', 'cancelled' => 'cancelled',
            'in progress', 'submitted' => 'processing', default => str_replace(' ', '_', $status),
        };

        $eInvoice->update([
            'status' => $status,
            'myinvois_uuid' => $summary['uuid'] ?? $eInvoice->myinvois_uuid,
            'long_id' => $summary['longId'] ?? $eInvoice->long_id,
            'validated_at' => $status === 'valid' ? now() : $eInvoice->validated_at,
            'response_payload' => $response,
            'validation_errors' => isset($summary['error']) ? (array) $summary['error'] : null,
            'failure_reason' => $summary['documentStatusReason'] ?? $eInvoice->failure_reason,
        ]);

        $eInvoice->submission?->update([
            'status' => str_replace(' ', '_', strtolower((string) ($response['overallStatus'] ?? $status))),
            'valid_count' => $status === 'valid' ? 1 : 0,
            'invalid_count' => $status === 'invalid' ? 1 : 0,
            'response_payload' => $response,
            'completed_at' => in_array($status, ['valid', 'invalid', 'cancelled'], true) ? now() : null,
        ]);

        if ($status === 'valid' && ! $eInvoice->invoice->locked_at) $eInvoice->invoice->update(['locked_at' => now()]);

        if (in_array($status, ['valid', 'invalid'], true) && ! $eInvoice->notified_at) {
            $user = $eInvoice->invoice->client?->user;
            if ($user) {
                $user->notify(new EInvoiceStatusNotification($eInvoice->fresh(['invoice'])));
                $eInvoice->update(['notified_at' => now()]);
            }
            return;
        }

        if ($status === 'processing') self::dispatch($eInvoice->id)->delay(now()->addSeconds(5));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Automated MyInvois polling failed.', ['einvoice_id' => $this->eInvoiceId, 'message' => $exception->getMessage()]);
    }
}
