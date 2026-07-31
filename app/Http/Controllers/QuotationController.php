<?php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\DocumentNumberGenerator;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotationController extends Controller
{
    private array $taxTypes = [
        'none',
        'sst',
        'service_tax',
        'sales_tax',
        'tourism_tax',
        'exempt',
        'zero_rated',
        'other',
    ];

    public function index(Request $request)
    {
        $clients = $this->availableClients()->orderBy('name')->get();
        $quotations = $this->filteredQuotations($request)
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('quotations.index', compact('quotations', 'clients'));
    }

    public function export(Request $request)
    {
        $quotations = $this->filteredQuotations($request)->get();

        return response()->streamDownload(function () use ($quotations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Quotation #', 'Client', 'Employee', 'Sub Total', 'Discount', 'Tax Type', 'Tax Rate', 'Tax Amount', 'Total', 'Date', 'Expiry Date', 'Status']);

            foreach ($quotations as $quotation) {
                fputcsv($handle, [
                    $quotation->quote_number,
                    $quotation->client->name ?? 'N/A',
                    $quotation->employee->name ?? 'N/A',
                    $quotation->sub_total,
                    $quotation->discount_amount,
                    $quotation->tax_type,
                    $quotation->tax_rate,
                    $quotation->tax_amount,
                    $quotation->total_amount,
                    optional($quotation->date)->format('Y-m-d'),
                    optional($quotation->expiry_date)->format('Y-m-d'),
                    $quotation->status,
                ]);
            }

            fclose($handle);
        }, 'quotations.csv', ['Content-Type' => 'text/csv']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $quotations = Quotation::with('client')->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($quotations as $quotation) {
            if (! $this->canManageQuotation($quotation)) {
                continue;
            }
            if ($quotation->isLocked()) {
                continue;
            }
            $quotation->items()->delete();
            $quotation->delete();
            $deleted++;
        }

        return redirect()->route('quotations.index')->with('success', $deleted.' quotation(s) deleted successfully.');
    }

    public function create()
    {
        $clients = $this->availableClients()->get();
        return view('quotations.create', compact('clients'));
    }

    public function edit(Quotation $quotation)
    {
        abort_unless($this->canManageQuotation($quotation), 403, 'Unauthorized action.');
        if ($quotation->isLocked()) {
            return redirect()->route('quotations.show', $quotation)->with('warning', 'Converted quotations are locked and cannot be edited.');
        }
        $clients = $this->availableClients()->get();
        return view('quotations.edit', compact('quotation', 'clients'));
    }

    public function delete(Quotation $quotation)
    {
        return $this->destroy($quotation);
    }

    public function destroy(Quotation $quotation)
    {
        abort_unless($this->canManageQuotation($quotation), 403, 'Unauthorized action.');
        if ($quotation->isLocked()) {
            return back()->with('warning', 'Converted quotations are locked and cannot be deleted.');
        }

        DB::beginTransaction();
        try {
            $quotation->items()->delete();
            $quotation->delete();
            DB::commit();
            return redirect()->route('quotations.index')->with('success', 'Quotation deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting quotation: '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to delete quotation.');
        }
    }

    public function store(Request $request, DocumentNumberGenerator $numberGenerator)
    {
        $request->validate($this->rules());

        $selectedClient = Client::find($request->client_id);
        if (! $selectedClient || ! $this->canUseClient($selectedClient)) {
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try {
            $totals = $this->calculateTotals($request);
            $quoteNumber = $numberGenerator->generate(
                new Quotation,
                'quote_number',
                'quotation_prefix',
                'quotation_number_format',
                'QT',
                \Illuminate\Support\Carbon::parse($request->date),
                Auth::id(),
                'quote_number'
            );

            $quotation = Quotation::create([
                'client_id' => $request->client_id,
                'quote_number' => $quoteNumber,
                'date' => $request->date,
                'expiry_date' => $request->expiry_date,
                'status' => 'draft',
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'employee_id' => Auth::id(),
            ]);

            foreach ($request->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                ]);
            }

            DB::commit();
            return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating quotation: '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to create quotation.')->withInput();
        }
    }

    public function show(Quotation $quotation)
    {
        abort_unless($this->canManageQuotation($quotation), 403, 'Unauthorized action.');
        return view('quotations.show', compact('quotation'));
    }

    public function downloadPdf(Quotation $quotation, ActivityLogger $activityLogger)
    {
        abort_unless($this->canManageQuotation($quotation), 403, 'Unauthorized action.');

        $quotation->load('client', 'employee', 'items');
        $activityLogger->log('pdf.downloaded', 'Quotation PDF downloaded', $quotation);
        $pdf = Pdf::loadView('pdfs.quotation', ['quotation' => $quotation, 'settings' => Setting::allKeyed()]);
        return $pdf->download('quotation_'.$quotation->quote_number.'.pdf');
    }

    public function update(Request $request, Quotation $quotation)
    {
        abort_unless($this->canManageQuotation($quotation), 403, 'Unauthorized action.');
        if ($quotation->isLocked()) {
            return redirect()->route('quotations.show', $quotation)->with('warning', 'Converted quotations are locked and cannot be edited.');
        }

        $request->validate($this->rules(true));
        $selectedClient = Client::find($request->client_id);
        if (! $selectedClient || ! $this->canUseClient($selectedClient)) {
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try {
            $totals = $this->calculateTotals($request);

            $quotation->update([
                'client_id' => $request->client_id,
                'date' => $request->date,
                'expiry_date' => $request->expiry_date,
                'status' => $request->status,
                'sub_total' => $totals['sub_total'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
            ]);

            $quotation->items()->delete();
            foreach ($request->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                ]);
            }

            DB::commit();
            return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating quotation: '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to update quotation.')->withInput();
        }
    }

    private function rules(bool $updating = false): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:date',
            'status' => $updating ? 'required|string|in:draft,sent,approved,rejected,converted_to_invoice' : 'nullable',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0.01',
            'sub_total' => 'required|numeric|min:0',
            'discount_type' => 'nullable|string|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_type' => 'nullable|string|in:'.implode(',', $this->taxTypes),
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'total_amount' => 'required|numeric|min:0',
        ];
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

    private function filteredQuotations(Request $request)
    {
        $query = Quotation::query()
            ->with('client', 'employee')
            ->when(Auth::user()?->isEmployee(), fn ($builder) => $builder->where('employee_id', Auth::id()));

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('quote_number', 'like', "%{$search}%")
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

        $sort = in_array($request->input('sort'), ['date', 'expiry_date', 'total_amount', 'status', 'created_at'], true) ? $request->input('sort') : 'created_at';
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

    private function canManageQuotation(Quotation $quotation): bool
    {
        return Auth::user()?->isAdmin() || (Auth::user()?->isEmployee() && $quotation->employee_id === Auth::id());
    }
}
