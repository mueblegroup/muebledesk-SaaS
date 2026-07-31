<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\ExpenseNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = $this->filteredExpenses($request)
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString();

        $summary = $this->expenseSummary($request);
        $categories = Expense::categoryOptions();

        return view('expenses.index', compact('expenses', 'summary', 'categories'));
    }

    public function create()
    {
        return view('expenses.create', ['expense' => new Expense(['expense_date' => now(), 'currency' => 'MYR', 'is_tax_deductible' => true]), 'categories' => Expense::categoryOptions()]);
    }

    public function store(Request $request, ExpenseNumberGenerator $numberGenerator, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->rules());

        $expense = Expense::create(array_merge($validated, [
            'recorded_by_user_id' => Auth::id(),
            'expense_number' => $numberGenerator->generate(Carbon::parse($validated['expense_date'])),
            'is_billable' => $request->boolean('is_billable'),
            'is_tax_deductible' => $request->boolean('is_tax_deductible'),
        ]));

        $activityLogger->log('expense.created', 'Expense recorded '.$expense->expense_number, $expense, [], $expense->toArray());

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense recorded successfully.');
    }

    public function show(Expense $expense)
    {
        $expense->load('recordedBy:id,name,email');
        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense)
    {
        return view('expenses.edit', ['expense' => $expense, 'categories' => Expense::categoryOptions()]);
    }

    public function update(Request $request, Expense $expense, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->rules());
        $old = $expense->toArray();

        $expense->update(array_merge($validated, [
            'is_billable' => $request->boolean('is_billable'),
            'is_tax_deductible' => $request->boolean('is_tax_deductible'),
        ]));

        $activityLogger->log('expense.updated', 'Expense updated '.$expense->expense_number, $expense, $old, $expense->fresh()->toArray());

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense, ActivityLogger $activityLogger)
    {
        $old = $expense->toArray();
        $expense->delete();
        $activityLogger->log('expense.deleted', 'Expense deleted '.$expense->expense_number, null, $old, []);

        return redirect()->route('expenses.index')->with('success', 'Expense deleted successfully.');
    }

    public function export(Request $request)
    {
        $expenses = $this->filteredExpenses($request)->get();

        return response()->streamDownload(function () use ($expenses) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Expense #', 'Date', 'Category', 'Vendor', 'Description', 'Amount', 'Currency', 'Payment Method', 'Reference', 'Billable', 'Tax Deductible', 'Recorded By']);

            foreach ($expenses as $expense) {
                fputcsv($handle, [
                    $expense->expense_number,
                    optional($expense->expense_date)->format('Y-m-d'),
                    $expense->category,
                    $expense->vendor,
                    $expense->description,
                    $expense->amount,
                    $expense->currency,
                    $expense->payment_method,
                    $expense->reference_number,
                    $expense->is_billable ? 'Yes' : 'No',
                    $expense->is_tax_deductible ? 'Yes' : 'No',
                    $expense->recordedBy->name ?? 'N/A',
                ]);
            }

            fclose($handle);
        }, 'expenses.csv', ['Content-Type' => 'text/csv']);
    }

    public function profitLoss(Request $request)
    {
        $period = $request->input('period') === 'all_time' ? 'all_time' : 'year';
        $year = (int) $request->input('year', now()->year);
        $month = $period === 'year' && $request->filled('month') ? (int) $request->input('month') : null;

        if ($period === 'all_time') {
            $firstPaymentDate = Payment::query()->min('payment_date');
            $firstExpenseDate = Expense::query()->min('expense_date');
            $earliestDate = collect([$firstPaymentDate, $firstExpenseDate])->filter()->sort()->first();
            $rangeStart = $earliestDate ? Carbon::parse($earliestDate)->startOfDay() : now()->startOfDay();
            $rangeEnd = now()->endOfDay();
        } else {
            $rangeStart = Carbon::create($year, $month ?: 1, 1)->startOfDay();
            $rangeEnd = $month ? $rangeStart->copy()->endOfMonth() : $rangeStart->copy()->endOfYear();
        }

        $revenue = Payment::query()->whereBetween('payment_date', [$rangeStart, $rangeEnd])->sum('amount');
        $expenses = Expense::query()->whereBetween('expense_date', [$rangeStart, $rangeEnd])->sum('amount');
        $netProfit = $revenue - $expenses;

        $expensesByCategory = Expense::query()
            ->select('category', DB::raw('SUM(amount) as total'))
            ->whereBetween('expense_date', [$rangeStart, $rangeEnd])
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $breakdownStart = $period === 'all_time'
            ? $rangeStart->copy()->startOfMonth()
            : Carbon::create($year, 1, 1)->startOfMonth();
        $breakdownEnd = $period === 'all_time'
            ? $rangeEnd->copy()->startOfMonth()
            : Carbon::create($year, 12, 1)->startOfMonth();

        $monthlyProfitLoss = collect();
        for ($cursor = $breakdownStart->copy(); $cursor->lte($breakdownEnd); $cursor->addMonth()) {
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth();
            $monthRevenue = Payment::whereBetween('payment_date', [$start, $end])->sum('amount');
            $monthExpenses = Expense::whereBetween('expense_date', [$start, $end])->sum('amount');

            $monthlyProfitLoss->push([
                'month' => $period === 'all_time' ? $start->format('M Y') : $start->format('M'),
                'revenue' => (float) $monthRevenue,
                'expenses' => (float) $monthExpenses,
                'net_profit' => (float) ($monthRevenue - $monthExpenses),
            ]);
        }

        return view('expenses.profit-loss', compact('period', 'year', 'month', 'rangeStart', 'rangeEnd', 'revenue', 'expenses', 'netProfit', 'expensesByCategory', 'monthlyProfitLoss'));
    }

    private function rules(): array
    {
        return [
            'expense_date' => ['required', 'date'],
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'currency' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function filteredExpenses(Request $request)
    {
        $query = Expense::query()->with('recordedBy:id,name,email');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('expense_number', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('expense_date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('expense_date', '<=', $to);
        }

        $sort = in_array($request->input('sort'), ['expense_date', 'amount', 'category', 'created_at'], true) ? $request->input('sort') : 'expense_date';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction);
    }

    private function expenseSummary(Request $request): array
    {
        $base = $this->filteredExpenses($request)->toBase();

        return [
            'total' => (float) (clone $base)->sum('amount'),
            'tax_deductible' => (float) (clone $base)->where('is_tax_deductible', true)->sum('amount'),
            'billable' => (float) (clone $base)->where('is_billable', true)->sum('amount'),
        ];
    }
}
