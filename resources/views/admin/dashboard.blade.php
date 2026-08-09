<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Admin Dashboard</h1>
            <p class="hidden text-sm text-slate-500 dark:text-slate-400 sm:block">Production overview and system actions.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <a href="{{ route('invoices.index') }}" class="stat-card">
                <span class="text-sm font-bold text-slate-500 dark:text-slate-400">Outstanding</span>
                <p class="mt-3 text-3xl font-black text-slate-950 dark:text-white">RM {{ number_format($outstandingAmount, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pending and partially paid invoices</p>
            </a>

            <a href="{{ route('payments.index') }}" class="stat-card">
                <span class="text-sm font-bold text-slate-500 dark:text-slate-400">Revenue This Month</span>
                <p class="mt-3 text-3xl font-black text-emerald-600 dark:text-emerald-400">RM {{ number_format($paymentsThisMonth, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Recorded payments</p>
            </a>

            <a href="{{ route('expenses.index') }}" class="stat-card">
                <span class="text-sm font-bold text-slate-500 dark:text-slate-400">Expenses This Month</span>
                <p class="mt-3 text-3xl font-black text-red-600 dark:text-red-400">RM {{ number_format($expensesThisMonth, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Company spending</p>
            </a>

            <a href="{{ route('reports.profit_loss') }}" class="stat-card">
                <span class="text-sm font-bold text-slate-500 dark:text-slate-400">Net This Month</span>
                <p class="mt-3 text-3xl font-black {{ $netProfitThisMonth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">RM {{ number_format($netProfitThisMonth, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Revenue minus expenses</p>
            </a>

            <a href="{{ route('invoices.index', ['status' => 'overdue']) }}" class="stat-card">
                <span class="text-sm font-bold text-slate-500 dark:text-slate-400">Overdue</span>
                <p class="mt-3 text-3xl font-black text-red-600 dark:text-red-400">{{ $overdueInvoices }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Invoices needing follow-up</p>
            </a>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <div class="card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="section-title">Needs Attention</h3>
                        <p class="section-subtitle">Latest unpaid invoices across the company.</p>
                    </div>
                    <a href="{{ route('invoices.index') }}" class="text-sm font-bold text-indigo-600 dark:text-indigo-400">All invoices</a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($recentInvoices as $invoice)
                        <a href="{{ route('invoices.show', $invoice) }}" class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 p-4 transition hover:bg-white hover:shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-900">
                            <div>
                                <p class="font-bold text-slate-950 dark:text-white">{{ $invoice->invoice_number }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $invoice->client?->name ?? 'No client' }} · Due {{ optional($invoice->due_date)->format('d M Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-black text-slate-950 dark:text-white">RM {{ number_format(max(0, $invoice->total_amount - $invoice->amount_paid), 2) }}</p>
                                <p class="text-xs uppercase text-slate-500">{{ str_replace('_', ' ', $invoice->status) }}</p>
                            </div>
                        </a>
                    @empty
                        <x-empty-state title="No open invoices" message="There are no pending or partially paid invoices right now." />
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="section-title">Recent Expenses</h3>
                        <p class="section-subtitle">Latest company spending.</p>
                    </div>
                    <a href="{{ route('expenses.index') }}" class="text-sm font-bold text-indigo-600 dark:text-indigo-400">All expenses</a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($recentExpenses as $expense)
                        <a href="{{ route('expenses.show', $expense) }}" class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 p-4 transition hover:bg-white hover:shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-900">
                            <div>
                                <p class="font-bold text-slate-950 dark:text-white">{{ $expense->description }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ str($expense->category)->replace('_', ' ')->title() }} · {{ optional($expense->expense_date)->format('d M Y') }}</p>
                            </div>
                            <p class="font-black text-red-600 dark:text-red-400">RM {{ number_format($expense->amount, 2) }}</p>
                        </a>
                    @empty
                        <x-empty-state title="No expenses yet" message="Recorded expenses will appear here." />
                    @endforelse
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <a href="{{ route('clients.index') }}" class="btn-secondary">Manage Clients</a>
            <a href="{{ route('quotations.index') }}" class="btn-secondary">Manage Quotations</a>
            <a href="{{ route('invoices.index') }}" class="btn-secondary">Manage Invoices</a>
            <a href="{{ route('expenses.create') }}" class="btn-secondary">Record Expense</a>
            <a href="{{ route('reports.profit_loss') }}" class="btn-secondary">P&L Report</a>
            <a href="{{ route('admin.activity-logs.index') }}" class="btn-secondary">Activity Logs</a>
        </section>
    </div>
</x-app-layout>
