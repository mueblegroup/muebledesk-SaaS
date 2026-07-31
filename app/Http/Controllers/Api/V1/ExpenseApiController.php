<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Expense;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\ExpenseNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseApiController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Expense::query()->with('recordedBy:id,name,email');
        $this->applyFilters($query, $request);

        $items = $query->orderByDesc('expense_date')->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->ok($items->items(), $this->paginationMeta($items));
    }

    public function show(Expense $expense)
    {
        return $this->ok($expense->load('recordedBy:id,name,email'));
    }

    public function store(Request $request, ExpenseNumberGenerator $numberGenerator, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->rules());

        $expense = Expense::create(array_merge($validated, [
            'recorded_by_user_id' => $request->input('recorded_by_user_id'),
            'expense_number' => $validated['expense_number'] ?? $numberGenerator->generate(Carbon::parse($validated['expense_date'])),
            'is_billable' => (bool) ($validated['is_billable'] ?? false),
            'is_tax_deductible' => (bool) ($validated['is_tax_deductible'] ?? true),
        ]));

        $activityLogger->log('expense.created', 'Expense created via API '.$expense->expense_number, $expense, [], $expense->toArray());

        return $this->created($expense->fresh()->load('recordedBy:id,name,email'));
    }

    public function update(Request $request, Expense $expense, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->rules(true));
        $old = $expense->toArray();

        $expense->update(array_merge($validated, [
            'is_billable' => (bool) ($validated['is_billable'] ?? false),
            'is_tax_deductible' => (bool) ($validated['is_tax_deductible'] ?? true),
        ]));

        $activityLogger->log('expense.updated', 'Expense updated via API '.$expense->expense_number, $expense, $old, $expense->fresh()->toArray());

        return $this->ok($expense->fresh()->load('recordedBy:id,name,email'));
    }

    public function destroy(Expense $expense, ActivityLogger $activityLogger)
    {
        $old = $expense->toArray();
        $expense->delete();
        $activityLogger->log('expense.deleted', 'Expense deleted via API '.$expense->expense_number, null, $old, []);

        return $this->deleted();
    }

    public function profitLoss(Request $request)
    {
        $year = (int) $request->query('year', now()->year);
        $month = $request->filled('month') ? (int) $request->query('month') : null;
        $start = Carbon::create($year, $month ?: 1, 1)->startOfDay();
        $end = $month ? $start->copy()->endOfMonth() : $start->copy()->endOfYear();

        $revenue = (float) Payment::whereBetween('payment_date', [$start, $end])->sum('amount');
        $expenses = (float) Expense::whereBetween('expense_date', [$start, $end])->sum('amount');
        $byCategory = Expense::select('category', DB::raw('SUM(amount) as total'))
            ->whereBetween('expense_date', [$start, $end])
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return $this->ok([
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'year' => $year,
                'month' => $month,
            ],
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_profit' => $revenue - $expenses,
            'expenses_by_category' => $byCategory,
        ]);
    }

    private function rules(bool $updating = false): array
    {
        return [
            'expense_number' => [$updating ? 'prohibited' : 'nullable', 'string', 'max:255', 'unique:expenses,expense_number'],
            'recorded_by_user_id' => ['nullable', 'exists:users,id'],
            'expense_date' => ['required', 'date'],
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'is_billable' => ['nullable', 'boolean'],
            'is_tax_deductible' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('expense_number', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }
        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->query('to'));
        }
    }
}
