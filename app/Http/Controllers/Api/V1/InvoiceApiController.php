<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\ActivityLogger;
use App\Services\DocumentNumberGenerator;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceApiController extends BaseApiController
{
    private array $taxTypes = ['none', 'sst', 'service_tax', 'sales_tax', 'tourism_tax', 'exempt', 'zero_rated', 'other'];

    public function index(Request $request)
    {
        $query = Invoice::query()->with('client', 'employee:id,name,email', 'items', 'payments.receipt');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->query('client_id'));
        }

        $invoices = $query->latest()->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->ok($invoices->items(), $this->paginationMeta($invoices));
    }

    public function show(Invoice $invoice)
    {
        return $this->ok($invoice->load('client', 'employee:id,name,email', 'items', 'payments.receipt'));
    }

    public function store(Request $request, DocumentNumberGenerator $numberGenerator, PaymentGatewayService $paymentGateway, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->rules());

        $invoice = DB::transaction(function () use ($validated, $numberGenerator, $paymentGateway, $activityLogger) {
            $client = Client::findOrFail($validated['client_id']);
            $totals = $this->calculateTotals($validated);

            $invoice = Invoice::create([
                'client_id' => $client->id,
                'invoice_number' => $validated['invoice_number'] ?? $numberGenerator->generate(new Invoice, 'invoice_number', 'invoice_prefix', 'invoice_number_format', 'INV', Carbon::parse($validated['date']), (int) ($validated['employee_id'] ?? $client->employee_id ?? 0), 'invoice_number'),
                'date' => $validated['date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'] ?? 'pending',
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'amount_paid' => 0,
                'employee_id' => $validated['employee_id'] ?? $client->employee_id,
            ]);

            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => round((float) $item['quantity'] * (float) $item['price'], 2),
                ]);
            }

            if ($paymentLink = $paymentGateway->createPaymentLink($invoice)) {
                $invoice->update(['payment_link' => $paymentLink]);
            }

            $activityLogger->log('invoice.created', 'Invoice created via API', $invoice, [], $invoice->toArray());

            return $invoice;
        });

        return $this->created($invoice->fresh()->load('client', 'items'));
    }

    public function update(Request $request, Invoice $invoice, PaymentGatewayService $paymentGateway, ActivityLogger $activityLogger)
    {
        abort_if($invoice->isLocked(), 409, 'Invoice is locked because payment history exists.');
        $validated = $request->validate($this->rules(true));

        DB::transaction(function () use ($validated, $invoice, $paymentGateway, $activityLogger) {
            $old = $invoice->fresh()->toArray();
            $totals = $this->calculateTotals($validated);

            $invoice->update([
                'client_id' => $validated['client_id'],
                'date' => $validated['date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'] ?? $invoice->status,
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'employee_id' => $validated['employee_id'] ?? $invoice->employee_id,
            ]);

            $invoice->items()->delete();
            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => round((float) $item['quantity'] * (float) $item['price'], 2),
                ]);
            }

            $invoice->update(['payment_link' => $paymentGateway->createPaymentLink($invoice)]);
            $activityLogger->log('invoice.updated', 'Invoice updated via API', $invoice, $old, $invoice->fresh()->toArray());
        });

        return $this->ok($invoice->fresh()->load('client', 'items'));
    }

    public function destroy(Invoice $invoice, ActivityLogger $activityLogger)
    {
        abort_if($invoice->isLocked(), 409, 'Invoice is locked because payment history exists.');
        $old = $invoice->toArray();
        $invoice->items()->delete();
        $invoice->delete();
        $activityLogger->log('invoice.deleted', 'Invoice deleted via API', null, $old, []);

        return $this->deleted();
    }

    public function recordPayment(Request $request, Invoice $invoice, ActivityLogger $activityLogger)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'transaction_id' => ['nullable', 'string', 'max:255', 'unique:payments,transaction_id'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_deposit' => ['nullable', 'boolean'],
        ]);

        $payment = DB::transaction(function () use ($invoice, $validated, $activityLogger) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $remaining = max(0, (float) $invoice->total_amount - (float) $invoice->amount_paid);
            abort_if((float) $validated['amount'] > $remaining, 422, 'Payment amount exceeds outstanding balance.');

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'transaction_id' => $validated['transaction_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_deposit' => (bool) ($validated['is_deposit'] ?? false),
            ]);

            PaymentReceipt::firstOrCreate(['payment_id' => $payment->id], [
                'receipt_number' => app(DocumentNumberGenerator::class)->generate(new PaymentReceipt, 'receipt_number', 'receipt_prefix', 'receipt_number_format', 'REC', $payment->payment_date, (int) ($invoice->employee_id ?? 0), 'receipt_number'),
                'date' => $payment->payment_date,
                'amount' => $payment->amount,
            ]);

            $invoice->amount_paid = $invoice->payments()->sum('amount');
            $invoice->status = $invoice->amount_paid >= $invoice->total_amount ? 'paid' : ($invoice->amount_paid > 0 ? 'partially_paid' : 'pending');
            $invoice->locked_at ??= now();
            if ($invoice->status === 'paid') {
                $invoice->payment_link = null;
            }
            $invoice->save();

            $activityLogger->log('payment.recorded', 'Payment recorded via API for invoice '.$invoice->invoice_number, $payment, [], $payment->toArray());

            return $payment;
        });

        return $this->created($payment->fresh()->load('receipt', 'invoice'));
    }

    private function rules(bool $updating = false): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'invoice_number' => [$updating ? 'prohibited' : 'nullable', 'string', 'max:255', 'unique:invoices,invoice_number'],
            'date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:date'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'overdue', 'partially_paid'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['nullable', Rule::in($this->taxTypes)],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employee_id' => ['nullable', 'exists:users,id'],
        ];
    }

    private function calculateTotals(array $data): array
    {
        $subTotal = collect($data['items'])->sum(fn ($item) => (float) $item['quantity'] * (float) $item['price']);
        $discountType = $data['discount_type'] ?? null;
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountAmount = $discountType === 'percentage' ? ($subTotal * $discountValue) / 100 : ($discountType === 'fixed' ? $discountValue : 0);
        $discountAmount = min($discountAmount, $subTotal);
        $taxableAmount = max(0, $subTotal - $discountAmount);
        $taxType = $data['tax_type'] ?? 'none';
        $taxRate = in_array($taxType, ['none', 'exempt', 'zero_rated'], true) ? 0 : (float) ($data['tax_rate'] ?? 0);
        $taxAmount = round(($taxableAmount * $taxRate) / 100, 2);

        return [
            'sub_total' => round($subTotal, 2),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => round($discountAmount, 2),
            'tax_type' => $taxType,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => round($taxableAmount + $taxAmount, 2),
        ];
    }
}
