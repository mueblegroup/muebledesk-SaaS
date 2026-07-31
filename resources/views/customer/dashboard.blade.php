<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Customer Dashboard</h1>
            <p class="hidden text-sm text-slate-500 dark:text-slate-400 sm:block">Invoices, payments, and account access.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <a href="{{ route('invoices.customer_index') }}" class="stat-card md:col-span-2">
                <span class="text-sm font-bold text-slate-500 dark:text-slate-400">Amount Due</span>
                <p class="mt-3 text-4xl font-black text-slate-950 dark:text-white">RM {{ number_format($outstandingAmount, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pending, partially paid, and overdue invoices</p>
            </a>

            <a href="{{ route('invoices.customer_index', ['status' => 'paid']) }}" class="stat-card">
                <span class="text-sm font-bold text-slate-500 dark:text-slate-400">Paid Invoices</span>
                <p class="mt-3 text-4xl font-black text-emerald-600 dark:text-emerald-400">{{ $paidInvoicesCount }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Completed invoice records</p>
            </a>
        </section>

        <section class="card">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="section-title">Open Invoices</h3>
                    <p class="section-subtitle">Invoices waiting for payment or confirmation.</p>
                </div>
                <a href="{{ route('invoices.customer_index') }}" class="text-sm font-bold text-indigo-600 dark:text-indigo-400">View all</a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse($openInvoices as $invoice)
                    <a href="{{ route('invoices.customer_show', $invoice) }}" class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 p-4 transition hover:bg-white hover:shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-900">
                        <div>
                            <p class="font-bold text-slate-950 dark:text-white">{{ $invoice->invoice_number }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Due {{ optional($invoice->due_date)->format('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-black text-slate-950 dark:text-white">RM {{ number_format(max(0, $invoice->total_amount - $invoice->amount_paid), 2) }}</p>
                            <p class="text-xs uppercase text-slate-500">{{ str_replace('_', ' ', $invoice->status) }}</p>
                        </div>
                    </a>
                @empty
                    <x-empty-state title="No open invoices" message="You do not have any invoices requiring action right now." />
                @endforelse
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('invoices.customer_index') }}" class="btn-primary">View My Invoices</a>
            <a href="{{ route('profile.edit') }}" class="btn-secondary">Update Profile / Password</a>
        </section>
    </div>
</x-app-layout>
