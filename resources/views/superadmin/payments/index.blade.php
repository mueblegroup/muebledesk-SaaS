<x-superadmin-layout>
    <x-slot name="title">Payments</x-slot>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-violet-600">Revenue control</p><h1 class="mt-1 text-2xl font-black">Payments & subscriptions</h1></div></x-slot>

    <div class="space-y-7">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([['Paid revenue','RM '.number_format($totals['paid'],2)],['Failed payments',$totals['failed']],['Active subscriptions',$totals['active']],['Past due',$totals['past_due']]] as [$label,$value])
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm font-semibold text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-black">{{ $value }}</p></div>
            @endforeach
        </section>

        <form class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 sm:flex-row dark:border-slate-800 dark:bg-slate-900">
            <input name="search" value="{{ request('search') }}" placeholder="Company, invoice or payment ID" class="flex-1 rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950">
            <select name="status" class="rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"><option value="">All statuses</option><option value="paid" @selected(request('status')==='paid')>Paid</option><option value="failed" @selected(request('status')==='failed')>Failed</option></select>
            <button class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-bold text-white">Filter</button>
        </form>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="px-6 py-3">Date</th><th class="px-6 py-3">Company</th><th class="px-6 py-3">Plan</th><th class="px-6 py-3">Amount</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Provider reference</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($payments as $payment)<tr><td class="px-6 py-4">{{ $payment->created_at->format('d M Y H:i') }}</td><td class="px-6 py-4 font-bold">{{ $payment->company?->name ?? 'Deleted company' }}</td><td class="px-6 py-4">{{ $payment->plan?->name ?? '—' }}</td><td class="px-6 py-4 font-bold">{{ $payment->currency }} {{ number_format($payment->amount,2) }}</td><td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $payment->status==='paid'?'bg-emerald-50 text-emerald-700':'bg-red-50 text-red-700' }}">{{ ucfirst($payment->status) }}</span>@if($payment->failure_message)<p class="mt-2 max-w-sm text-xs text-red-600">{{ $payment->failure_message }}</p>@endif</td><td class="px-6 py-4 font-mono text-xs">{{ $payment->provider_invoice_id ?? $payment->provider_payment_id ?? '—' }}</td></tr>@empty<tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">No subscription payments recorded yet. New Stripe webhook events will appear here.</td></tr>@endforelse</tbody>
            </table></div><div class="border-t border-slate-200 p-5 dark:border-slate-800">{{ $payments->links() }}</div>
        </section>
    </div>
</x-superadmin-layout>
