<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Profit & Loss Report</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="section-title">P&L Summary</h3>
                <p class="section-subtitle">
                    {{ $period === 'all_time' ? 'All Time' : ($month ? $rangeStart->format('F Y') : (string) $year) }}
                    · {{ $rangeStart->format('Y-m-d') }} to {{ $rangeEnd->format('Y-m-d') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('expenses.profit_loss.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="btn-secondary">Export CSV</a>
                <a href="{{ route('expenses.profit_loss.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="btn-secondary">Export PDF</a>
                <a href="{{ route('expenses.index') }}" class="btn-secondary">Back to Expenses</a>
            </div>
        </div>

        <form method="GET" class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" x-data="{ period: @js($period) }">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label for="period" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Report Period</label>
                    <select id="period" name="period" x-model="period" class="block w-full">
                        <option value="year">Year / Month</option>
                        <option value="all_time">All Time</option>
                    </select>
                </div>

                <div x-show="period === 'year'" x-cloak>
                    <label for="year" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Year</label>
                    <input id="year" name="year" type="number" min="2000" max="2100" value="{{ $year }}" class="block w-full">
                </div>

                <div x-show="period === 'year'" x-cloak>
                    <label for="month" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Month</label>
                    <select id="month" name="month" class="block w-full">
                        <option value="">Full year</option>
                        @foreach (range(1, 12) as $monthNumber)
                            <option value="{{ $monthNumber }}" @selected($month === $monthNumber)>{{ \Carbon\Carbon::create($year, $monthNumber, 1)->format('F') }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="btn-secondary w-full">Generate Report</button>
                </div>
            </div>
        </form>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950">
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Revenue Collected</p>
                <p class="mt-2 text-3xl font-black text-emerald-900 dark:text-emerald-100">RM {{ number_format((float) $revenue, 2) }}</p>
            </div>
            <div class="rounded-3xl border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950">
                <p class="text-sm font-semibold text-red-700 dark:text-red-300">Company Expenses</p>
                <p class="mt-2 text-3xl font-black text-red-900 dark:text-red-100">RM {{ number_format((float) $expenses, 2) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-500">Net Profit / Loss</p>
                <p class="mt-2 text-3xl font-black {{ $netProfit >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">RM {{ number_format((float) $netProfit, 2) }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Expenses by Category</h4>
                <div class="mt-4 space-y-3">
                    @forelse ($expensesByCategory as $row)
                        <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-950">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ str($row->category)->replace('_', ' ')->title() }}</span>
                            <span class="text-sm font-black text-slate-950 dark:text-white">RM {{ number_format((float) $row->total, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No expenses in this period.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">{{ $period === 'all_time' ? 'All-Time Monthly Breakdown' : 'Monthly Breakdown' }}</h4>
                <div class="mt-4 max-h-[36rem] overflow-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="sticky top-0 bg-white dark:bg-slate-900"><tr><th class="py-2 text-left">Month</th><th class="py-2 text-right">Revenue</th><th class="py-2 text-right">Expenses</th><th class="py-2 text-right">Net</th></tr></thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach ($monthlyProfitLoss as $row)
                                <tr>
                                    <td class="py-2 font-semibold">{{ $row['month'] }}</td>
                                    <td class="py-2 text-right">{{ number_format($row['revenue'], 2) }}</td>
                                    <td class="py-2 text-right">{{ number_format($row['expenses'], 2) }}</td>
                                    <td class="py-2 text-right font-bold {{ $row['net_profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($row['net_profit'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
