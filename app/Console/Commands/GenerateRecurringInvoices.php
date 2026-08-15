<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\DocumentNumberGenerator;
use App\Services\PaymentGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring {--date= : Generate invoices due up to this date, format YYYY-MM-DD} {--dry-run : Show what would be generated without creating invoices}';
    protected $description = 'Generate actual invoices from active recurring invoice templates that are due in each company timezone.';

    public function handle(DocumentNumberGenerator $numberGenerator, ActivityLogger $activityLogger, PaymentGatewayService $paymentGateway): int
    {
        $forcedDate = $this->option('date') ? Carbon::parse($this->option('date'))->toDateString() : null;
        $dryRun = (bool) $this->option('dry-run');
        $generatedCount = 0;
        $skippedCount = 0;

        // A tenant can be up to one calendar day ahead of UTC. Pull a narrow
        // candidate set, then evaluate the due date again in that company's
        // own IANA timezone before generating anything.
        $candidateDate = $forcedDate ?: Carbon::now('UTC')->addDay()->toDateString();

        $due = RecurringInvoice::withoutGlobalScopes()
            ->whereNotNull('company_id')
            ->where('is_active', true)
            ->whereDate('next_invoice_date', '<=', $candidateDate)
            ->orderBy('company_id')
            ->orderBy('next_invoice_date')
            ->get(['id', 'company_id']);

        if ($due->isEmpty()) {
            $this->info('No recurring invoices are due.');
            return Command::SUCCESS;
        }

        foreach ($due as $dueTemplate) {
            $company = Company::query()->find($dueTemplate->company_id);
            if (! $company) {
                $skippedCount++;
                Log::warning('Recurring invoice skipped because its company no longer exists.', ['recurring_invoice_id' => $dueTemplate->id]);
                continue;
            }

            $companyTimezone = $company->timezone ?: 'UTC';
            $runDate = $forcedDate
                ? Carbon::parse($forcedDate, $companyTimezone)->startOfDay()
                : Carbon::now($companyTimezone)->startOfDay();

            app()->instance(Company::class, $company);
            app()->instance('currentCompany', $company);

            try {
                DB::transaction(function () use ($dueTemplate, $runDate, $companyTimezone, $dryRun, $numberGenerator, $activityLogger, $paymentGateway, &$generatedCount, &$skippedCount) {
                    $recurring = RecurringInvoice::query()->with(['client', 'items', 'company'])
                        ->whereKey($dueTemplate->id)->lockForUpdate()->first();

                    if (! $recurring || ! $recurring->is_active || $recurring->next_invoice_date?->greaterThan($runDate)) {
                        $skippedCount++;
                        return;
                    }

                    if (($recurring->end_date && $recurring->next_invoice_date->greaterThan($recurring->end_date)) || $recurring->items->isEmpty()) {
                        if (! $dryRun && $recurring->end_date && $recurring->next_invoice_date->greaterThan($recurring->end_date)) {
                            $recurring->update(['is_active' => false]);
                        }
                        $skippedCount++;
                        return;
                    }

                    $invoiceDate = $recurring->next_invoice_date->copy();
                    $dueDays = (int) ($recurring->client?->payment_terms_days ?? Setting::get('default_invoice_due_days', 14));
                    $dueDate = $invoiceDate->copy()->addDays(max(0, $dueDays));

                    if ($dryRun) {
                        $this->line('Would generate invoice for '.$recurring->client?->name.' in '.$recurring->company->name.' ('.$companyTimezone.', local date '.$runDate->toDateString().')');
                        return;
                    }

                    $invoice = Invoice::create([
                        'company_id' => $recurring->company_id,
                        'client_id' => $recurring->client_id,
                        'employee_id' => $recurring->employee_id,
                        'invoice_number' => $numberGenerator->generate(new Invoice, 'invoice_number', 'invoice_prefix', 'invoice_number_format', 'INV', $invoiceDate, (int) $recurring->employee_id, 'invoice_number'),
                        'date' => $invoiceDate,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                        'sub_total' => $recurring->sub_total,
                        'discount_type' => $recurring->discount_type,
                        'discount_value' => $recurring->discount_value,
                        'discount_amount' => $recurring->discount_amount,
                        'tax_type' => $recurring->tax_type ?: 'none',
                        'tax_rate' => $recurring->tax_rate ?? 0,
                        'tax_amount' => $recurring->tax_amount ?? 0,
                        'total_amount' => $recurring->total_amount,
                        'amount_paid' => 0,
                    ]);

                    foreach ($recurring->items as $item) {
                        $invoice->items()->create([
                            'company_id' => $recurring->company_id,
                            'item_name' => $item->item_name,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'total' => $item->total,
                        ]);
                    }

                    if ($paymentLink = $paymentGateway->createPaymentLink($invoice)) {
                        $invoice->update(['payment_link' => $paymentLink]);
                    }

                    $nextDate = $recurring->calculateNextInvoiceDate($invoiceDate);
                    $updates = ['next_invoice_date' => $nextDate];
                    if ($recurring->end_date && $nextDate->greaterThan($recurring->end_date)) {
                        $updates['is_active'] = false;
                    }
                    $recurring->update($updates);

                    $activityLogger->log('recurring_invoice.generated', 'Recurring invoice generated '.$invoice->invoice_number, $invoice, [], [
                        'recurring_invoice_id' => $recurring->id,
                        'next_invoice_date' => $nextDate->toDateString(),
                        'frequency' => $recurring->frequencyLabel(),
                        'company_timezone' => $companyTimezone,
                        'local_run_date' => $runDate->toDateString(),
                        'payment_gateway' => 'stripe',
                    ]);

                    $generatedCount++;
                });
            } catch (\Throwable $e) {
                $skippedCount++;
                Log::error('Recurring Invoice Generation Error', [
                    'recurring_invoice_id' => $dueTemplate->id,
                    'company_id' => $dueTemplate->company_id,
                    'company_timezone' => $companyTimezone,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            } finally {
                app()->forgetInstance('currentCompany');
                app()->forgetInstance(Company::class);
            }
        }

        $this->info("Finished. Generated: {$generatedCount}. Skipped: {$skippedCount}.");
        return Command::SUCCESS;
    }
}
