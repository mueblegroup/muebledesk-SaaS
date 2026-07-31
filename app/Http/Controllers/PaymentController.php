<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\DocumentNumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = $this->filteredPayments($request)
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('payments.index', compact('payments'));
    }

    public function export(Request $request)
    {
        $payments = $this->filteredPayments($request)->get();

        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Receipt #', 'Invoice #', 'Client', 'Employee', 'Amount', 'Date', 'Method', 'Reference', 'Attachment', 'Notes']);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->receipt->receipt_number ?? 'N/A',
                    $payment->invoice->invoice_number ?? 'N/A',
                    $payment->invoice->client->name ?? 'N/A',
                    $payment->invoice->employee->name ?? 'N/A',
                    $payment->amount,
                    optional($payment->payment_date)->format('Y-m-d'),
                    $payment->payment_method,
                    $payment->transaction_reference,
                    $payment->transfer_receipt_original_name,
                    $payment->notes,
                ]);
            }

            fclose($handle);
        }, 'payments.csv', ['Content-Type' => 'text/csv']);
    }

    public function manualCreate()
    {
        $this->requireCompanyUser();

        $invoices = Invoice::query()
            ->with('client:id,name')
            ->when(Auth::user()->isEmployee(), fn ($query) => $query->where('employee_id', Auth::id()))
            ->whereColumn('amount_paid', '<', 'total_amount')
            ->orderByDesc('date')
            ->get();

        return view('payments.create', [
            'payment' => new Payment(['payment_date' => now()]),
            'invoices' => $invoices,
            'selectedInvoice' => null,
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function manualStore(Request $request, ActivityLogger $activityLogger)
    {
        $this->requireCompanyUser();
        $request->validate(['invoice_id' => ['required', 'integer', 'exists:invoices,id']]);

        $invoice = Invoice::findOrFail($request->integer('invoice_id'));

        return $this->store($request, $invoice, $activityLogger);
    }

    public function create(Invoice $invoice)
    {
        $this->requireCompanyUser();
        $this->authorizeInvoiceAccess($invoice);

        return view('payments.create', [
            'payment' => new Payment(['payment_date' => now()]),
            'invoices' => collect([$invoice->loadMissing('client:id,name')]),
            'selectedInvoice' => $invoice,
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function store(Request $request, Invoice $invoice, ActivityLogger $activityLogger)
    {
        $this->requireCompanyUser();
        $this->authorizeInvoiceAccess($invoice);

        $validated = $request->validate($this->rules());

        DB::beginTransaction();
        $storedAttachment = null;

        try {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->authorizeInvoiceAccess($lockedInvoice);

            $outstanding = max(0, (float) $lockedInvoice->total_amount - (float) $lockedInvoice->amount_paid);
            if ($outstanding <= 0) {
                DB::rollBack();
                return back()->with('warning', 'This invoice is already fully paid.')->withInput();
            }

            if ((float) $validated['amount'] > $outstanding) {
                DB::rollBack();
                return back()->with('error', 'Payment amount cannot exceed the current outstanding balance of RM '.number_format($outstanding, 2).'.')->withInput();
            }

            if ($request->hasFile('transfer_receipt')) {
                $storedAttachment = $request->file('transfer_receipt')->store('payment-receipts/'.now()->format('Y/m'), 'public');
            }

            $payment = Payment::create([
                'invoice_id' => $lockedInvoice->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'transaction_id' => $validated['transaction_id'] ?? null,
                'transfer_receipt_path' => $storedAttachment,
                'transfer_receipt_original_name' => $request->file('transfer_receipt')?->getClientOriginalName(),
                'notes' => $validated['notes'] ?? null,
                'recorded_by_employee_id' => Auth::id(),
                'is_deposit' => $request->boolean('is_deposit'),
            ]);

            $this->ensureReceipt($payment);
            $this->recalculateInvoice($lockedInvoice);

            $activityLogger->log('payment.recorded', 'Payment recorded for invoice '.$lockedInvoice->invoice_number, $payment, [], $payment->toArray());
            DB::commit();

            return redirect()->route('payments.index')->with('success', 'Payment recorded successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            if ($storedAttachment) Storage::disk('public')->delete($storedAttachment);
            if ($e->getCode() == '23000' && Str::contains($e->getMessage(), 'payments_transaction_id_unique')) {
                return back()->with('error', 'The transaction ID already exists.')->withInput();
            }
            Log::error('Error recording payment for invoice '.$invoice->id.': '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to record payment.')->withInput();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($storedAttachment) Storage::disk('public')->delete($storedAttachment);
            Log::error('Error recording payment for invoice '.$invoice->id.': '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to record payment: '.$e->getMessage())->withInput();
        }
    }

    public function show(Payment $payment)
    {
        $this->authorizePaymentAccess($payment);
        return redirect()->route(Auth::user()?->isCustomer() ? 'invoices.customer_show' : 'invoices.show', $payment->invoice)
            ->with('status', 'Payment details are shown inside the invoice record.');
    }

    public function edit(Payment $payment)
    {
        $this->requireCompanyUser();
        $this->authorizePaymentAccess($payment);
        $payment->loadMissing('invoice.client');

        return view('payments.edit', [
            'payment' => $payment,
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function update(Request $request, Payment $payment, ActivityLogger $activityLogger)
    {
        $this->requireCompanyUser();
        $this->authorizePaymentAccess($payment);

        $validated = $request->validate($this->rules($payment));
        $newAttachment = null;
        $oldAttachment = $payment->transfer_receipt_path;

        DB::beginTransaction();

        try {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->whereKey($lockedPayment->invoice_id)->lockForUpdate()->firstOrFail();
            $this->authorizeInvoiceAccess($invoice);

            $otherPayments = (float) $invoice->payments()->whereKeyNot($lockedPayment->id)->sum('amount');
            $maximum = max(0, (float) $invoice->total_amount - $otherPayments);

            if ((float) $validated['amount'] > $maximum) {
                DB::rollBack();
                return back()->with('error', 'Payment amount cannot exceed RM '.number_format($maximum, 2).' after accounting for the other payments.')->withInput();
            }

            if ($request->hasFile('transfer_receipt')) {
                $newAttachment = $request->file('transfer_receipt')->store('payment-receipts/'.now()->format('Y/m'), 'public');
            }

            $old = $lockedPayment->toArray();
            $removeAttachment = $request->boolean('remove_transfer_receipt');

            $lockedPayment->update([
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'transaction_id' => $validated['transaction_id'] ?? null,
                'transfer_receipt_path' => $newAttachment ?: ($removeAttachment ? null : $lockedPayment->transfer_receipt_path),
                'transfer_receipt_original_name' => $newAttachment
                    ? $request->file('transfer_receipt')->getClientOriginalName()
                    : ($removeAttachment ? null : $lockedPayment->transfer_receipt_original_name),
                'notes' => $validated['notes'] ?? null,
                'is_deposit' => $request->boolean('is_deposit'),
            ]);

            $receipt = $this->ensureReceipt($lockedPayment);
            $receipt->update(['date' => $lockedPayment->payment_date, 'amount' => $lockedPayment->amount]);
            $this->recalculateInvoice($invoice);

            $activityLogger->log('payment.updated', 'Payment updated for invoice '.$invoice->invoice_number, $lockedPayment, $old, $lockedPayment->fresh()->toArray());
            DB::commit();

            if (($newAttachment || $removeAttachment) && $oldAttachment) {
                Storage::disk('public')->delete($oldAttachment);
            }

            return redirect()->route('payments.index')->with('success', 'Payment updated and invoice balance recalculated.');
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($newAttachment) Storage::disk('public')->delete($newAttachment);
            Log::error('Error updating payment '.$payment->id.': '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to update payment: '.$e->getMessage())->withInput();
        }
    }

    public function destroy(Payment $payment, ActivityLogger $activityLogger)
    {
        $this->requireCompanyUser();
        $this->authorizePaymentAccess($payment);
        $attachment = $payment->transfer_receipt_path;

        DB::transaction(function () use ($payment, $activityLogger) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $invoice = $payment->invoice()->lockForUpdate()->first();
            $oldPayment = $payment->toArray();
            $payment->delete();

            if ($invoice) $this->recalculateInvoice($invoice);

            $activityLogger->log('payment.deleted', 'Payment deleted from invoice '.$invoice?->invoice_number, $invoice, $oldPayment, ['invoice_amount_paid' => $invoice?->amount_paid]);
        });

        if ($attachment) Storage::disk('public')->delete($attachment);

        return redirect()->route('payments.index')->with('success', 'Payment deleted and invoice balance updated.');
    }

    public function downloadReceipt(Payment $payment, ActivityLogger $activityLogger)
    {
        $this->authorizePaymentAccess($payment);
        $payment->load('invoice.client', 'invoice.employee', 'recordedBy');
        $receipt = $this->ensureReceipt($payment);
        $activityLogger->log('pdf.downloaded', 'Payment receipt PDF downloaded', $payment);

        return Pdf::loadView('pdfs.payment-receipt', [
            'payment' => $payment,
            'receipt' => $receipt,
            'settings' => Setting::allKeyed(),
        ])->download('receipt_'.$receipt->receipt_number.'.pdf');
    }

    private function rules(?Payment $payment = null): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'string', Rule::in($this->paymentMethods())],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'transaction_id' => ['nullable', 'string', 'max:255', Rule::unique('payments', 'transaction_id')->ignore($payment?->id)],
            'transfer_receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'remove_transfer_receipt' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_deposit' => ['nullable', 'boolean'],
        ];
    }

    private function recalculateInvoice(Invoice $invoice): void
    {
        $invoice->amount_paid = min((float) $invoice->total_amount, (float) $invoice->payments()->sum('amount'));
        $invoice->status = $invoice->amount_paid >= $invoice->total_amount
            ? 'paid'
            : ($invoice->amount_paid > 0 ? 'partially_paid' : 'pending');
        $invoice->locked_at = $invoice->amount_paid > 0 ? ($invoice->locked_at ?: now()) : null;
        $invoice->payment_link = $invoice->status === 'paid' ? null : $invoice->payment_link;
        $invoice->save();
    }

    private function ensureReceipt(Payment $payment): PaymentReceipt
    {
        return PaymentReceipt::firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'receipt_number' => app(DocumentNumberGenerator::class)->generate(
                    new PaymentReceipt,
                    'receipt_number',
                    'receipt_prefix',
                    'receipt_number_format',
                    'REC',
                    $payment->payment_date,
                    (int) ($payment->recorded_by_employee_id ?? $payment->invoice?->employee_id ?? 0),
                    'receipt_number'
                ),
                'date' => $payment->payment_date,
                'amount' => $payment->amount,
            ]
        );
    }

    private function filteredPayments(Request $request)
    {
        $query = Payment::query()->with('invoice.client', 'invoice.employee', 'receipt');

        if (Auth::user()->isEmployee()) {
            $query->whereHas('invoice', fn ($invoice) => $invoice->where('employee_id', Auth::id()));
        } elseif (Auth::user()->isCustomer()) {
            $clientId = optional(Auth::user()->clients)->id;
            $query->whereHas('invoice', fn ($invoice) => $invoice->where('client_id', $clientId ?: 0));
        } elseif (! Auth::user()->isAdmin()) {
            $query->whereRaw('1 = 0');
        }

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('payment_method', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
                    ->orWhere('transfer_receipt_original_name', 'like', "%{$search}%")
                    ->orWhereHas('receipt', fn ($receipt) => $receipt->where('receipt_number', 'like', "%{$search}%"))
                    ->orWhereHas('invoice', function ($invoice) use ($search) {
                        $invoice->where('invoice_number', 'like', "%{$search}%")
                            ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($method = $request->input('payment_method')) $query->where('payment_method', $method);
        if ($from = $request->input('from')) $query->whereDate('payment_date', '>=', $from);
        if ($to = $request->input('to')) $query->whereDate('payment_date', '<=', $to);

        $sort = in_array($request->input('sort'), ['payment_date', 'amount', 'payment_method', 'created_at'], true) ? $request->input('sort') : 'payment_date';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction);
    }

    private function authorizeInvoiceAccess(Invoice $invoice): void
    {
        if (Auth::user()->isAdmin()) return;
        if (Auth::user()->isEmployee() && (int) $invoice->employee_id !== (int) Auth::id()) abort(403, 'Unauthorized action.');
        if (Auth::user()->isCustomer() && (int) $invoice->client_id !== (int) optional(Auth::user()->clients)->id) abort(403, 'Unauthorized action.');
        if (! Auth::user()->isEmployee() && ! Auth::user()->isCustomer()) abort(403, 'Unauthorized action.');
    }

    private function authorizePaymentAccess(Payment $payment): void
    {
        $payment->loadMissing('invoice.client');
        $this->authorizeInvoiceAccess($payment->invoice);
    }

    private function requireCompanyUser(): void
    {
        abort_unless(Auth::user()?->isEmployee() || Auth::user()?->isAdmin(), 403, 'Company access only.');
    }

    private function paymentMethods(): array
    {
        return ['hitpay', 'stripe', 'bank_transfer', 'cash', 'credit_card', 'cheque', 'online_payment'];
    }
}
