<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Expenses</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="section-title">Company Spending</h3>
                <p class="section-subtitle">Track operating costs and feed your profit & loss reports.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('expenses.profit_loss') }}" class="btn-secondary">P&L Report</a>
                <a href="{{ route('expenses.create') }}" class="btn-primary">Record Expense</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-500">Filtered Spend</p>
                <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">RM {{ number_format($summary['total'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-500">Tax Deductible</p>
                <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">RM {{ number_format($summary['tax_deductible'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-500">Billable / Rechargeable</p>
                <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">RM {{ number_format($summary['billable'], 2) }}</p>
            </div>
        </div>

        <form method="GET" class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-4 md:grid-cols-5">
                <input name="q" value="{{ request('q') }}" placeholder="Search expenses..." class="block w-full">
                <select name="category" class="block w-full">
                    <option value="">All categories</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <input name="from" type="date" value="{{ request('from') }}" class="block w-full">
                <input name="to" type="date" value="{{ request('to') }}" class="block w-full">
                <div class="flex gap-2">
                    <button class="btn-secondary" type="submit">Filter</button>
                    <a href="{{ route('expenses.export', request()->query()) }}" class="btn-secondary">Export CSV</a>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Expense</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Vendor</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Amount</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">{{ optional($expense->expense_date)->format('Y-m-d') }}</td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('expenses.show', $expense) }}" class="font-bold text-indigo-600 hover:underline">{{ $expense->expense_number }}</a>
                                    <p class="text-sm text-slate-500">{{ $expense->description }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">{{ str($expense->category)->replace('_', ' ')->title() }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $expense->vendor ?: '—' }}</td>
                                <td class="px-4 py-4 text-right text-sm font-bold text-slate-950 dark:text-white">{{ $expense->currency }} {{ number_format((float) $expense->amount, 2) }}</td>
                                <td class="px-4 py-4 text-right text-sm">
                                    <a href="{{ route('expenses.edit', $expense) }}" class="font-semibold text-indigo-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No expenses recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $expenses->links() }}</div>
        </div>
    </div>
</x-app-layout>
