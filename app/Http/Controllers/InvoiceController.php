<?php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\DocumentNumberGenerator;
use App\Services\PaymentGatewayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    private array $taxTypes = [
        'none', 'sst', 'service_tax', 'sales_tax', 'tourism_tax', 'exempt', 'zero_rated', 'other',
    ];

    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user?->isCustomer()) {
            return $this->customerIndex($request);
        }

        $this->requireCompanyUser();
        $clients = $this->availableClients()->orderBy('name')->get();
        $invoices = $this->filteredInvoices($request)
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('invoices.index', compact('invoices', 'clients'));
    }

    public function customerIndex(Request $request)
    {
        $client = Auth::user()->clients;
        if (Auth::user()->role->value !== 'customer' || ! $client) {
            abort(403, 'Unauthorized action or no client associated.');
        }

        $invoices = $this->filteredCustomerInvoices($request, $client->id)
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('invoices.customer_index', compact('invoices'));
    }

    public function export(Request $request)
    {
        $this->requireCompanyUser();
        $invoices = $this->filteredInvoices($request)->get();
        return $this->streamInvoiceCsv($invoices, 'invoices.csv');
    }

    public function customerExport(Request $request)
    {
        $client = Auth::user()->clients;
        if (! $client) {
            abort(403, 'No client associated.');
        }

        return $this->streamInvoiceCsv($this->filteredCustomerInvoices($request, $client->id)->get(), 'my-invoices.csv');
    }

    public function bulkDestroy(Request $request, ActivityLogger $activityLogger)
    {
        $this->requireCompanyUser();
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $invoices = Invoice::query()
            ->when(Auth::user()?->isEmployee(), fn ($query) => $query->where('employee_id', Auth::id()))
            ->whereIn('id', $ids)
            ->get();
        $deleted = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->isLocked()) {
                continue;
            }
            DB::transaction(function () use ($invoice, &$deleted, $activityLogger) {
                $old = $invoice->toArray();
                $invoice->items()->delete();
                $invoice->payments()->delete();
                $invoice->delete();
                $deleted++;
                $activityLogger->log('invoice.deleted', 'Invoice deleted', null, $old, []);
            });
        }

        return redirect()->route('invoices.index')->with('success', $deleted.' invoice(s) deleted successfully.');
    }

    public function create()
    {
        $this->requireCompanyUser();
        $clients = $this->availableClients()->get();
        return view('invoices.create', compact('clients'));
    }

    public function createFromQuotation(Quotation $quotation)
    {
        abort_unless($this->canManageQuotation($quotation), 403, 'Unauthorized action.');
        if ($quotation->isLocked()) {
            return back()->with('warning', 'This quotation has already been converted and is locked.');
        }

        $quotation->load('client', 'items');
        return view('invoices.create-from-quotation', compact('quotation'));
    }

    public function store(Request $request, DocumentNumberGenerator $numberGenerator, ActivityLogger $activityLogger, PaymentGatewayService $paymentGateway)
    {
        $this->requireCompanyUser();
        $request->validate($this->rules());

        $selectedClient = Client::find($request->client_id);
        if (! $selectedClient || ! $this->canUseClient($selectedClient)) {
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try {
            $totals = $this->calculateTotals($request);
            $quotation = null;

            if ($request->filled('quotation_id') && $quotation = Quotation::find($request->quotation_id)) {
                if (! $this->canManageQuotation($quotation) || $quotation->isLocked()) {
                    abort(409, 'This quotation cannot be converted.');
                }
                $totals = [
                    'sub_total' => (float) $quotation->sub_total,
                    'discount_type' => $quotation->discount_type,
                    'discount_value' => (float) $quotation->discount_value,
                    'discount_amount' => (float) ($quotation->discount_amount ?? 0),
                    'tax_type' => $quotation->tax_type ?: 'none',
                    'tax_rate' => (float) ($quotation->tax_rate ?? 0),
                    'tax_amount' => (float) ($quotation->tax_amount ?? 0),
                    'total_amount' => (float) $quotation->total_amount,
                ];
            }

            $ownerId = $selectedClient->employee_id ?: Auth::id();

            $invoice = Invoice::create([
                'client_id' => $request->client_id,
                'invoice_number' => $numberGenerator->generate(
                    new Invoice,
                    'invoice_number',
                    'invoice_prefix',
                    'invoice_number_format',
                    'INV',
                    \Illuminate\Support\Carbon::parse($request->date),
                    Auth::id(),
                    'invoice_number'
                ),
                'date' => $request->date,
                'due_date' => $request->due_date,
                'status' => 'pending',
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'amount_paid' => 0.00,
                'quotation_id' => $request->quotation_id,
                'employee_id' => $ownerId,
            ]);

            foreach ($request->items as $item) {
                $invoice->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                ]);
            }

            if ($paymentLink = $paymentGateway->createPaymentLink($invoice)) {
                $invoice->update(['payment_link' => $paymentLink]);
            }

            $activityLogger->log('invoice.created', 'Invoice created', $invoice, [], $invoice->toArray());

            if ($quotation) {
                $quotation->update(['status' => 'converted_to_invoice']);
                $activityLogger->log('quotation.converted', 'Quotation converted to invoice '.$invoice->invoice_number, $quotation, [], ['invoice_id' => $invoice->id]);
            }

            DB::commit();

            return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating invoice: '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to create invoice.')->withInput();
        }
    }

    public function edit(Invoice $invoice)
    {
        $this->requireCompanyUser();
        abort_unless($this->canManageInvoice($invoice), 403, 'Unauthorized action.');
        if ($invoice->isLocked()) {
            return redirect()->route('invoices.show', $invoice)->with('warning', 'Invoices with recorded payments are locked and cannot be edited.');
        }
        $clients = $this->availableClients()->get();
        $invoice->load('client', 'items');
        return view('invoices.edit', compact('invoice', 'clients'));
    }

    public function update(Request $request, Invoice $invoice, ActivityLogger $activityLogger, PaymentGatewayService $paymentGateway)
    {
        $this->requireCompanyUser();
        abort_unless($this->canManageInvoice($invoice), 403, 'Unauthorized action.');
        if ($invoice->isLocked()) {
            return redirect()->route('invoices.show', $invoice)->with('warning', 'Invoices with recorded payments are locked and cannot be edited.');
        }

        $request->validate($this->rules(true));
        $selectedClient = Client::find($request->client_id);
        if (! $selectedClient || ! $this->canUseClient($selectedClient)) {
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try {
            $old = $invoice->fresh()->toArray();
            $totals = $this->calculateTotals($request);

            $invoice->update([
                'client_id' => $request->client_id,
                'date' => $request->date,
                'due_date' => $request->due_date,
                'status' => $request->status,
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
            ]);

            $invoice->items()->delete();
            foreach ($request->items as $item) {
                $invoice->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                ]);
            }

            if (($invoice->total_amount - $invoice->amount_paid) > 0) {
                $invoice->update(['payment_link' => $paymentGateway->createPaymentLink($invoice)]);
            } else {
                $invoice->update(['payment_link' => null]);
            }

            $activityLogger->log('invoice.updated', 'Invoice updated', $invoice, $old, $invoice->fresh()->toArray());

            DB::commit();
            return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating invoice: '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to update invoice.')->withInput();
        }
    }

    public function show(Invoice $invoice)
    {
        $this->authorizeInvoiceView($invoice);
        $invoice->load('client', 'employee', 'items', 'payments.receipt', 'eInvoice.submission');
        return view('invoices.show', compact('invoice'));
    }

    public function customerShow(Invoice $invoice)
    {
        $this->authorizeInvoiceView($invoice);
        $invoice->load('client', 'employee', 'items', 'payments.receipt');
        return view('invoices.customer_show', compact('invoice'));
    }

    public function downloadPdf(Invoice $invoice, ActivityLogger $activityLogger)
    {
        $this->authorizeInvoiceView($invoice);
        $invoice->load('client', 'employee', 'items', 'payments');
        $activityLogger->log('pdf.downloaded', 'Invoice PDF downloaded', $invoice);
        return Pdf::loadView('pdfs.invoice', ['invoice' => $invoice, 'settings' => Setting::allKeyed()])->download('invoice_'.$invoice->invoice_number.'.pdf');
    }

    public function customerDownloadPdf(Invoice $invoice, ActivityLogger $activityLogger)
    {
        $this->authorizeInvoiceView($invoice);
        $invoice->load('client', 'employee', 'items', 'payments');
        $activityLogger->log('pdf.downloaded', 'Invoice PDF downloaded by customer', $invoice);
        return Pdf::loadView('pdfs.invoice', ['invoice' => $invoice, 'settings' => Setting::allKeyed()])->download('invoice_'.$invoice->invoice_number.'.pdf');
    }

    public function destroy(Invoice $invoice, ActivityLogger $activityLogger)
    {
        $this->requireCompanyUser();
        abort_unless($this->canManageInvoice($invoice), 403, 'Unauthorized action.');
        if ($invoice->isLocked()) {
            return back()->with('warning', 'Invoices with recorded payments are locked and cannot be deleted.');
        }

        DB::beginTransaction();
        try {
            $old = $invoice->toArray();
            $invoice->items()->delete();
            $invoice->payments()->delete();
            $invoice->delete();
            $activityLogger->log('invoice.deleted', 'Invoice deleted', null, $old, []);
            DB::commit();
            return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting invoice: '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to delete invoice.');
        }
    }

    private function rules(bool $updating = false): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:date',
            'status' => $updating ? 'required|string|in:pending,paid,overdue,partially_paid' : 'nullable',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:invoice_items,id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0.01',
            'items.*.total' => 'required|numeric|min:0',
            'sub_total' => 'required|numeric|min:0',
            'discount_type' => 'nullable|string|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_type' => 'nullable|string|in:'.implode(',', $this->taxTypes),
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'total_amount' => 'required|numeric|min:0',
        ];
    }

    private function requireCompanyUser(): void
    {
        abort_unless(Auth::user()?->isEmployee() || Auth::user()?->isAdmin(), 403, 'Company access only.');
    }

    private function requireEmployee(): void
    {
        $this->requireCompanyUser();
    }

    private function authorizeInvoiceView(Invoice $invoice): void
    {
        if (Auth::user()?->isAdmin()) {
            return;
        }

        if (Auth::user()?->isEmployee() && $invoice->employee_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if (Auth::user()?->isCustomer() && $invoice->client_id !== optional(Auth::user()->clients)->id) {
            abort(403, 'Unauthorized action.');
        }

        if (! Auth::user()?->isEmployee() && ! Auth::user()?->isCustomer()) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function calculateTotals(Request $request): array
    {
        $subTotal = collect($request->items)->sum(fn ($item) => (float) $item['quantity'] * (float) $item['price']);
        $discountType = $request->discount_type ?: null;
        $discountValue = (float) $request->discount_value;
        $discountAmount = $discountType === 'percentage'
            ? ($subTotal * $discountValue) / 100
            : ($discountType === 'fixed' ? $discountValue : 0);
        $discountAmount = min($discountAmount, $subTotal);
        $taxableAmount = max(0, $subTotal - $discountAmount);
        $taxType = $request->tax_type ?: 'none';
        $taxRate = in_array($taxType, ['none', 'exempt', 'zero_rated'], true) ? 0 : (float) $request->tax_rate;
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

    private function filteredInvoices(Request $request)
    {
        $query = Invoice::query()->with('client', 'employee', 'eInvoice.submission');

        if (Auth::user()?->isEmployee()) {
            $query->where('employee_id', Auth::id());
        } elseif (Auth::user()?->isCustomer()) {
            $query->where('client_id', optional(Auth::user()->clients)->id ?: 0);
        }

        return $this->applyInvoiceFilters($query, $request);
    }

    private function filteredCustomerInvoices(Request $request, int $clientId)
    {
        return $this->applyInvoiceFilters(Invoice::query()->with('client')->where('client_id', $clientId), $request);
    }

    private function applyInvoiceFilters($query, Request $request)
    {
        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
            });
        }

        if ($clientId = $request->input('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('date', '<=', $to);
        }

        $sort = in_array($request->input('sort'), ['date', 'due_date', 'total_amount', 'status', 'created_at'], true) ? $request->input('sort') : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction);
    }

    private function streamInvoiceCsv($invoices, string $filename)
    {
        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice #', 'Client', 'Employee', 'Sub Total', 'Discount', 'Tax Type', 'Tax Rate', 'Tax Amount', 'Total', 'Paid', 'Date', 'Due Date', 'Status']);
            foreach ($invoices as $invoice) {
                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->client->name ?? 'N/A',
                    $invoice->employee->name ?? 'N/A',
                    $invoice->sub_total,
                    $invoice->discount_amount,
                    $invoice->tax_type,
                    $invoice->tax_rate,
                    $invoice->tax_amount,
                    $invoice->total_amount,
                    $invoice->amount_paid,
                    optional($invoice->date)->format('Y-m-d'),
                    optional($invoice->due_date)->format('Y-m-d'),
                    $invoice->status,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
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

    private function canManageQuotation(Quotation $quotation): bool
    {
        return Auth::user()?->isAdmin() || (Auth::user()?->isEmployee() && $quotation->employee_id === Auth::id());
    }
}
