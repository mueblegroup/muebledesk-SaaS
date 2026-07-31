<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRoleEnum;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\RecurringInvoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DocumentNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SimpleResourceApiController extends BaseApiController
{
    public function quotations(Request $request)
    {
        $query = Quotation::query()->with('client', 'employee:id,name,email', 'items');
        if ($search = trim((string) $request->query('q'))) {
            $query->where('quote_number', 'like', "%{$search}%")->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
        }
        $items = $query->latest()->paginate(min((int) $request->query('per_page', 25), 100));
        return $this->ok($items->items(), $this->paginationMeta($items));
    }

    public function showQuotation(Quotation $quotation)
    {
        return $this->ok($quotation->load('client', 'employee:id,name,email', 'items'));
    }

    public function storeQuotation(Request $request, DocumentNumberGenerator $numberGenerator, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->documentRules('quotations'));
        $quotation = DB::transaction(function () use ($validated, $numberGenerator, $activityLogger) {
            $totals = $this->calculateTotals($validated);
            $quotation = Quotation::create([
                'client_id' => $validated['client_id'],
                'quote_number' => $validated['quote_number'] ?? $numberGenerator->generate(new Quotation, 'quote_number', 'quotation_prefix', 'quotation_number_format', 'QT', Carbon::parse($validated['date']), (int) ($validated['employee_id'] ?? 0), 'quote_number'),
                'date' => $validated['date'],
                'expiry_date' => $validated['expiry_date'],
                'status' => $validated['status'] ?? 'draft',
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'total_amount' => $totals['total_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'employee_id' => $validated['employee_id'] ?? null,
            ]);
            foreach ($validated['items'] as $item) {
                $quotation->items()->create($this->lineItemPayload($item));
            }
            $activityLogger->log('quotation.created', 'Quotation created via API', $quotation, [], $quotation->toArray());
            return $quotation;
        });
        return $this->created($quotation->fresh()->load('client', 'items'));
    }

    public function updateQuotation(Request $request, Quotation $quotation, ActivityLogger $activityLogger)
    {
        abort_if($quotation->isLocked(), 409, 'Quotation is locked.');
        $validated = $request->validate($this->documentRules('quotations', true));
        DB::transaction(function () use ($validated, $quotation, $activityLogger) {
            $old = $quotation->toArray();
            $totals = $this->calculateTotals($validated);
            $quotation->update([
                'client_id' => $validated['client_id'],
                'date' => $validated['date'],
                'expiry_date' => $validated['expiry_date'],
                'status' => $validated['status'] ?? $quotation->status,
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'total_amount' => $totals['total_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'employee_id' => $validated['employee_id'] ?? $quotation->employee_id,
            ]);
            $quotation->items()->delete();
            foreach ($validated['items'] as $item) {
                $quotation->items()->create($this->lineItemPayload($item));
            }
            $activityLogger->log('quotation.updated', 'Quotation updated via API', $quotation, $old, $quotation->fresh()->toArray());
        });
        return $this->ok($quotation->fresh()->load('client', 'items'));
    }

    public function deleteQuotation(Quotation $quotation, ActivityLogger $activityLogger)
    {
        abort_if($quotation->isLocked(), 409, 'Quotation is locked.');
        $old = $quotation->toArray();
        $quotation->items()->delete();
        $quotation->delete();
        $activityLogger->log('quotation.deleted', 'Quotation deleted via API', null, $old, []);
        return $this->deleted();
    }

    public function payments(Request $request)
    {
        $query = Payment::query()->with('invoice.client', 'receipt');
        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->query('invoice_id'));
        }
        $items = $query->latest()->paginate(min((int) $request->query('per_page', 25), 100));
        return $this->ok($items->items(), $this->paginationMeta($items));
    }

    public function showPayment(Payment $payment)
    {
        return $this->ok($payment->load('invoice.client', 'receipt'));
    }

    public function deletePayment(Payment $payment, ActivityLogger $activityLogger)
    {
        DB::transaction(function () use ($payment, $activityLogger) {
            $invoice = $payment->invoice()->lockForUpdate()->first();
            $old = $payment->toArray();
            $payment->delete();
            if ($invoice) {
                $invoice->amount_paid = $invoice->payments()->sum('amount');
                $invoice->status = $invoice->amount_paid >= $invoice->total_amount ? 'paid' : ($invoice->amount_paid > 0 ? 'partially_paid' : 'pending');
                $invoice->save();
            }
            $activityLogger->log('payment.deleted', 'Payment deleted via API', $invoice, $old, ['invoice_amount_paid' => $invoice?->amount_paid]);
        });
        return $this->deleted();
    }

    public function recurringInvoices(Request $request)
    {
        $query = RecurringInvoice::query()->with('client', 'employee:id,name,email', 'items');
        $items = $query->latest()->paginate(min((int) $request->query('per_page', 25), 100));
        return $this->ok($items->items(), $this->paginationMeta($items));
    }

    public function showRecurringInvoice(RecurringInvoice $recurringInvoice)
    {
        return $this->ok($recurringInvoice->load('client', 'employee:id,name,email', 'items'));
    }

    public function storeRecurringInvoice(Request $request, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->recurringRules());
        $recurringInvoice = DB::transaction(function () use ($validated, $activityLogger) {
            $totals = $this->calculateTotals($validated);
            $recurringInvoice = RecurringInvoice::create([
                'client_id' => $validated['client_id'],
                'invoice_prefix' => $validated['invoice_prefix'] ?? null,
                'frequency' => $validated['frequency'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'next_invoice_date' => $validated['start_date'],
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'employee_id' => $validated['employee_id'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
            foreach ($validated['items'] as $item) {
                $recurringInvoice->items()->create($this->lineItemPayload($item));
            }
            $activityLogger->log('recurring_invoice.created', 'Recurring invoice template created via API', $recurringInvoice, [], $recurringInvoice->toArray());
            return $recurringInvoice;
        });
        return $this->created($recurringInvoice->fresh()->load('client', 'items'));
    }

    public function updateRecurringInvoice(Request $request, RecurringInvoice $recurringInvoice, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->recurringRules(true));
        DB::transaction(function () use ($validated, $recurringInvoice, $activityLogger) {
            $old = $recurringInvoice->toArray();
            $totals = $this->calculateTotals($validated);
            $recurringInvoice->update([
                'client_id' => $validated['client_id'],
                'invoice_prefix' => $validated['invoice_prefix'] ?? null,
                'frequency' => $validated['frequency'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'employee_id' => $validated['employee_id'] ?? $recurringInvoice->employee_id,
                'is_active' => (bool) ($validated['is_active'] ?? $recurringInvoice->is_active),
            ]);
            if (! empty($validated['reset_next_invoice_date'])) {
                $recurringInvoice->update(['next_invoice_date' => $validated['start_date']]);
            }
            $recurringInvoice->items()->delete();
            foreach ($validated['items'] as $item) {
                $recurringInvoice->items()->create($this->lineItemPayload($item));
            }
            $activityLogger->log('recurring_invoice.updated', 'Recurring invoice template updated via API', $recurringInvoice, $old, $recurringInvoice->fresh()->toArray());
        });
        return $this->ok($recurringInvoice->fresh()->load('client', 'items'));
    }

    public function deleteRecurringInvoice(RecurringInvoice $recurringInvoice, ActivityLogger $activityLogger)
    {
        $old = $recurringInvoice->toArray();
        $recurringInvoice->items()->delete();
        $recurringInvoice->delete();
        $activityLogger->log('recurring_invoice.deleted', 'Recurring invoice template deleted via API', null, $old, []);
        return $this->deleted();
    }

    public function users(Request $request)
    {
        $query = User::query()->select('id', 'name', 'email', 'role', 'created_at', 'updated_at');
        $items = $query->latest()->paginate(min((int) $request->query('per_page', 25), 100));
        return $this->ok($items->items(), $this->paginationMeta($items));
    }

    public function storeUser(Request $request, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->userRules());
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);
        $activityLogger->log('user.created', 'User created via API', $user, [], $user->only(['id', 'name', 'email', 'role']));
        return $this->created($user->only(['id', 'name', 'email', 'role', 'created_at']));
    }

    public function updateUser(Request $request, User $user, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->userRules($user, true));
        $old = $user->only(['id', 'name', 'email', 'role']);
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();
        $activityLogger->log('user.updated', 'User updated via API', $user, $old, $user->only(['id', 'name', 'email', 'role']));
        return $this->ok($user->only(['id', 'name', 'email', 'role', 'updated_at']));
    }

    public function settings()
    {
        return $this->ok(collect(Setting::allKeyed())->except(['hitpay_api_key', 'hitpay_salt', 'hitpay_webhook_salt', 'stripe_secret_key', 'stripe_webhook_secret'])->toArray());
    }

    public function activityLogs(Request $request)
    {
        $query = ActivityLog::query()->with('actor:id,name,email')->latest();
        if ($request->filled('event')) {
            $query->where('event', $request->query('event'));
        }
        $items = $query->paginate(min((int) $request->query('per_page', 25), 100));
        return $this->ok($items->items(), $this->paginationMeta($items));
    }

    private function documentRules(string $table, bool $updating = false): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'quote_number' => [$table === 'quotations' && ! $updating ? 'nullable' : 'prohibited', 'string', 'max:255', 'unique:quotations,quote_number'],
            'date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:date'],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted_to_invoice'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['nullable', Rule::in(['none', 'sst', 'service_tax', 'sales_tax', 'tourism_tax', 'exempt', 'zero_rated', 'other'])],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employee_id' => ['nullable', 'exists:users,id'],
        ];
    }

    private function recurringRules(bool $updating = false): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'invoice_prefix' => ['nullable', 'string', 'max:50'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['nullable', Rule::in(['none', 'sst', 'service_tax', 'sales_tax', 'tourism_tax', 'exempt', 'zero_rated', 'other'])],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employee_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
            'reset_next_invoice_date' => ['nullable', 'boolean'],
        ];
    }

    private function userRules(?User $user = null, bool $updating = false): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_map(fn ($role) => $role->value, UserRoleEnum::cases()))],
            'password' => [$updating ? 'nullable' : 'required', 'string', 'min:8'],
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

    private function lineItemPayload(array $item): array
    {
        return [
            'item_name' => $item['item_name'],
            'description' => $item['description'] ?? null,
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'total' => (float) $item['quantity'] * (float) $item['price'],
        ];
    }
}
