<x-client-portal-layout :company="$company">
    <x-slot name="title">Invoices & Receipts</x-slot>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600">Client portal</p>
            <h1 class="mt-1 text-2xl font-black">Invoices & payment receipts</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $company->name }}</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900">
            <div>
                <h2 class="text-lg font-black text-slate-900 dark:text-white">Plan billing history</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Invoices are the original Stripe billing documents. Payment receipts are generated from MuebleDesk's recorded successful subscription payments.</p>
            </div>
            <a href="{{ route('client-portal.billing.index', $company) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">Back to plans & billing</a>
        </div>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @if($payments->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-base font-bold text-slate-800 dark:text-slate-100">No subscription billing records yet.</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Paid plan invoices and payment receipts will appear here after Stripe confirms the billing event.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950/60">
                            <tr class="text-left text-xs font-black uppercase tracking-wide text-slate-500">
                                <th class="px-5 py-4">Date</th>
                                <th class="px-5 py-4">Plan / description</th>
                                <th class="px-5 py-4">Amount</th>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">Documents</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($payments as $payment)
                                @php
                                    $metadata = $payment->metadata ?? [];
                                    $hasInvoice = filled($metadata['hosted_invoice_url'] ?? null) || filled($metadata['invoice_pdf'] ?? null);
                                    $displayDate = $payment->paid_at ?? $payment->failed_at ?? $payment->created_at;
                                    $statusClass = match($payment->status) {
                                        'paid' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
                                        'failed' => 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300',
                                        default => 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
                                    };
                                @endphp
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-5 py-5 text-sm text-slate-600 dark:text-slate-300">{{ $displayDate?->format('d M Y H:i') ?? '—' }}</td>
                                    <td class="px-5 py-5">
                                        <p class="font-bold text-slate-900 dark:text-white">{{ $payment->plan?->name ?? $payment->description ?? 'Subscription billing' }}</p>
                                        @if($payment->description && $payment->description !== $payment->plan?->name)
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $payment->description }}</p>
                                        @endif
                                        @if($payment->provider_invoice_id)
                                            <p class="mt-1 text-[11px] text-slate-400">Invoice ref: {{ $payment->provider_invoice_id }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-5 text-sm font-black text-slate-900 dark:text-white">{{ strtoupper($payment->currency) }} {{ number_format((float) $payment->amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-5"><span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ $statusClass }}">{{ ucfirst($payment->status) }}</span></td>
                                    <td class="px-5 py-5">
                                        <div class="flex min-w-max flex-wrap gap-2">
                                            @if($hasInvoice)
                                                <a href="{{ route('client-portal.billing.history.invoice', [$company, $payment]) }}" target="_blank" rel="noopener" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-300">View invoice</a>
                                            @else
                                                <span class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-400 dark:bg-slate-800">Invoice unavailable</span>
                                            @endif

                                            @if($payment->status === 'paid')
                                                <a href="{{ route('client-portal.billing.history.receipt', [$company, $payment]) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">Download receipt</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($payments->hasPages())
                    <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $payments->links() }}</div>
                @endif
            @endif
        </section>
    </div>
</x-client-portal-layout>
