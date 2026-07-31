<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecurringInvoiceController extends Controller
{
    private array $taxTypes = ['none', 'sst', 'service_tax', 'sales_tax', 'tourism_tax', 'exempt', 'zero_rated', 'other'];

    public function index(Request $request)
    {
        $clients = $this->availableClients()->orderBy('name')->get();
        $recurringInvoices = $this->filteredRecurringInvoices($request)
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('recurring_invoices.index', compact('recurringInvoices', 'clients'));
    }

    public function export(Request $request)
    {
        $recurringInvoices = $this->filteredRecurringInvoices($request)->get();

        return response()->streamDownload(function () use ($recurringInvoices) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Client', 'Employee', 'Prefix', 'Sub Total', 'Discount', 'Tax Type', 'Tax Rate', 'Tax Amount', 'Total', 'Frequency', 'Start Date', 'Next Invoice', 'Status']);

            foreach ($recurringInvoices as $recurringInvoice) {
                fputcsv($handle, [
                    $recurringInvoice->client->name ?? 'N/A',
                    $recurringInvoice->employee->name ?? 'N/A',
                    $recurringInvoice->invoice_prefix,
                    $recurringInvoice->sub_total,
                    $recurringInvoice->discount_amount,
                    $recurringInvoice->tax_type,
                    $recurringInvoice->tax_rate,
                    $recurringInvoice->tax_amount,
                    $recurringInvoice->total_amount,
                    $recurringInvoice->frequencyLabel(),
                    optional($recurringInvoice->start_date)->format('Y-m-d'),
                    optional($recurringInvoice->next_invoice_date)->format('Y-m-d'),
                    $recurringInvoice->is_active ? 'Active' : 'Inactive',
                ]);
            }

            fclose($handle);
        }, 'recurring-invoices.csv', ['Content-Type' => 'text/csv']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $items = RecurringInvoice::query()
            ->when(Auth::user()?->isEmployee(), fn ($query) => $query->where('employee_id', Auth::id()))
            ->whereIn('id', $ids)
            ->get();

        foreach ($items as $item) {
            $item->delete();
        }

        return redirect()->route('recurring-invoices.index')->with('success', $items->count().' recurring invoice(s) deleted successfully.');
    }

    public function create()
    {
        $clients = $this->availableClients()->get();
        return view('recurring_invoices.create', compact('clients'));
    }

    public function createFromInvoice(Invoice $invoice)
    {
        abort_unless($this->canManageInvoice($invoice), 403, 'Unauthorized action.');
        $invoice->load('items');
        $clients = $this->availableClients()->get();
        return view('recurring_invoices.create', compact('invoice', 'clients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->recurringInvoiceValidationRules());
        $this->processRecurringInvoice($request, null, $validated);
        return redirect()->route('recurring-invoices.index')->with('success', 'Recurring invoice created successfully.');
    }

    public function storeFromInvoice(Request $request, Invoice $invoice)
    {
        abort_unless($this->canManageInvoice($invoice), 403, 'Unauthorized action.');
        $validated = $request->validate($this->recurringInvoiceValidationRules());
        $this->processRecurringInvoice($request, null, $validated);
        return redirect()->route('recurring-invoices.index')->with('success', 'Recurring invoice created from existing invoice successfully.');
    }

    public function show(RecurringInvoice $recurringInvoice)
    {
        abort_unless($this->canManageRecurring($recurringInvoice), 403, 'Unauthorized action.');
        $recurringInvoice->load('client', 'items');
        return view('recurring_invoices.show', compact('recurringInvoice'));
    }

    public function edit(RecurringInvoice $recurringInvoice)
    {
        abort_unless($this->canManageRecurring($recurringInvoice), 403, 'Unauthorized action.');
        $clients = $this->availableClients()->get();
        $recurringInvoice->load('items');
        return view('recurring_invoices.edit', compact('recurringInvoice', 'clients'));
    }

    public function update(Request $request, RecurringInvoice $recurringInvoice)
    {
        abort_unless($this->canManageRecurring($recurringInvoice), 403, 'Unauthorized action.');
        $validated = $request->validate($this->recurringInvoiceValidationRules());
        $this->processRecurringInvoice($request, $recurringInvoice, $validated);
        return redirect()->route('recurring-invoices.index')->with('success', 'Recurring invoice updated successfully.');
    }

    public function destroy(RecurringInvoice $recurringInvoice)
    {
        abort_unless($this->canManageRecurring($recurringInvoice), 403, 'Unauthorized action.');
        $recurringInvoice->delete();
        return redirect()->route('recurring-invoices.index')->with('success', 'Recurring invoice deleted successfully.');
    }

    public function toggleActive(RecurringInvoice $recurringInvoice)
    {
        abort_unless($this->canManageRecurring($recurringInvoice), 403, 'Unauthorized action.');
        $recurringInvoice->is_active = ! $recurringInvoice->is_active;
        $recurringInvoice->save();
        return back()->with('success', 'Recurring invoice status updated successfully.');
    }

    protected function recurringInvoiceValidationRules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id', function ($attribute, $value, $fail) {
                $client = Client::find($value);
                if (! $client || ! $this->canUseClient($client)) {
                    $fail('The selected client is invalid.');
                }
            }],
            'invoice_prefix' => 'nullable|string|max:20',
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'])],
            'repeat_every' => ['nullable', 'required_if:frequency,custom', 'integer', 'min:1', 'max:1200'],
            'repeat_unit' => ['nullable', 'required_if:frequency,custom', Rule::in(['days', 'weeks', 'months', 'years'])],
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string|max:10000',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0.01',
            'discount_type' => 'nullable|string|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_type' => 'nullable|string|in:'.implode(',', $this->taxTypes),
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    protected function processRecurringInvoice(Request $request, ?RecurringInvoice $recurringInvoice, array $validated): void
    {
        DB::transaction(function () use ($request, $recurringInvoice, $validated) {
            $totals = $this->calculateTotals($validated);
            $client = Client::find($validated['client_id']);
            $isCustom = $validated['frequency'] === 'custom';

            $data = [
                'client_id' => $validated['client_id'],
                'invoice_prefix' => $validated['invoice_prefix'] ?? null,
                'frequency' => $validated['frequency'],
                'repeat_every' => $isCustom ? (int) $validated['repeat_every'] : null,
                'repeat_unit' => $isCustom ? $validated['repeat_unit'] : null,
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
                'employee_id' => $client?->employee_id ?: Auth::id(),
                'is_active' => $request->boolean('is_active', true),
            ];

            if ($recurringInvoice) {
                $scheduleChanged = $recurringInvoice->frequency !== $data['frequency']
                    || (int) $recurringInvoice->repeat_every !== (int) ($data['repeat_every'] ?? 0)
                    || $recurringInvoice->repeat_unit !== $data['repeat_unit']
                    || optional($recurringInvoice->start_date)->format('Y-m-d') !== $data['start_date'];

                if ($scheduleChanged) {
                    $data['next_invoice_date'] = $data['start_date'];
                }

                $recurringInvoice->update($data);
                $recurringInvoice->items()->delete();
            } else {
                $data['next_invoice_date'] = $validated['start_date'];
                $recurringInvoice = RecurringInvoice::create($data);
            }

            foreach ($validated['items'] as $item) {
                $recurringInvoice->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => (float) $item['quantity'] * (float) $item['price'],
                ]);
            }
        });
    }

    private function calculateTotals(array $validated): array
    {
        $subTotal = collect($validated['items'])->sum(fn ($item) => (float) $item['quantity'] * (float) $item['price']);
        $discountType = $validated['discount_type'] ?? null;
        $discountValue = (float) ($validated['discount_value'] ?? 0);
        $discountAmount = $discountType === 'percentage' ? ($subTotal * $discountValue) / 100 : ($discountType === 'fixed' ? $discountValue : 0);
        $discountAmount = min($discountAmount, $subTotal);
        $taxableAmount = max(0, $subTotal - $discountAmount);
        $taxType = $validated['tax_type'] ?? 'none';
        $taxRate = in_array($taxType, ['none', 'exempt', 'zero_rated'], true) ? 0 : (float) ($validated['tax_rate'] ?? 0);
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

    private function filteredRecurringInvoices(Request $request)
    {
        $query = RecurringInvoice::query()
            ->with('client', 'employee')
            ->when(Auth::user()?->isEmployee(), fn ($builder) => $builder->where('employee_id', Auth::id()));

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('invoice_prefix', 'like', "%{$search}%")
                    ->orWhere('frequency', 'like', "%{$search}%")
                    ->orWhere('repeat_every', 'like', "%{$search}%")
                    ->orWhere('repeat_unit', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
            });
        }

        if ($clientId = $request->input('client_id')) $query->where('client_id', $clientId);
        if ($frequency = $request->input('frequency')) $query->where('frequency', $frequency);
        if ($request->filled('is_active')) $query->where('is_active', $request->input('is_active'));
        if ($from = $request->input('from')) $query->whereDate('start_date', '>=', $from);
        if ($to = $request->input('to')) $query->whereDate('start_date', '<=', $to);

        $sort = in_array($request->input('sort'), ['start_date', 'next_invoice_date', 'total_amount', 'frequency', 'created_at'], true) ? $request->input('sort') : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        return $query->orderBy($sort, $direction);
    }

    private function availableClients()
    {
        return Client::query()->when(Auth::user()?->isEmployee(), fn ($builder) => $builder->where('employee_id', Auth::id()));
    }

    private function canUseClient(Client $client): bool
    {
        return Auth::user()?->isAdmin() || (Auth::user()?->isEmployee() && $client->employee_id === Auth::id());
    }

    private function canManageInvoice(Invoice $invoice): bool
    {
        return Auth::user()?->isAdmin() || (Auth::user()?->isEmployee() && $invoice->employee_id === Auth::id());
    }

    private function canManageRecurring(RecurringInvoice $recurringInvoice): bool
    {
        return Auth::user()?->isAdmin() || (Auth::user()?->isEmployee() && $recurringInvoice->employee_id === Auth::id());
    }
}
