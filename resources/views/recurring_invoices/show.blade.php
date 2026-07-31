<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Recurring Invoice Schedule</h2>
    </x-slot>

    @php($upcomingDates = $recurringInvoice->upcomingInvoiceDates(12))

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="section-title">{{ $recurringInvoice->client->name ?? 'Recurring Invoice' }}</h3>
                <p class="section-subtitle">{{ $recurringInvoice->frequencyLabel() }} · RM {{ number_format($recurringInvoice->total_amount, 2) }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('recurring-invoices.index') }}" class="btn-secondary">Back</a>
                <a href="{{ route('recurring-invoices.edit', $recurringInvoice) }}" class="btn-primary">Edit Schedule</a>
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Frequency</p>
                <p class="mt-2 text-lg font-extrabold text-slate-950 dark:text-white">{{ $recurringInvoice->frequencyLabel() }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Next Invoice</p>
                <p class="mt-2 text-lg font-extrabold text-slate-950 dark:text-white">{{ $recurringInvoice->next_invoice_date?->format('d M Y') ?? 'Not scheduled' }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400">End Date</p>
                <p class="mt-2 text-lg font-extrabold text-slate-950 dark:text-white">{{ $recurringInvoice->end_date?->format('d M Y') ?? 'No end date' }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Status</p>
                <p class="mt-2 text-lg font-extrabold {{ $recurringInvoice->is_active ? 'text-emerald-600' : 'text-red-600' }}">{{ $recurringInvoice->is_active ? 'Active' : 'Inactive' }}</p>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-5">
                <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Upcoming Invoice Creation Dates</h4>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">These dates are calculated using the same recurrence rules as the scheduled invoice generator.</p>
            </div>

            @if ($upcomingDates->isEmpty())
                <div class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-500 dark:bg-slate-950 dark:text-slate-400">No upcoming invoice dates are available. The schedule may have ended or may not have a valid next date.</div>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($upcomingDates as $index => $date)
                        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-4 dark:border-indigo-900 dark:bg-indigo-950/30">
                            <p class="text-xs font-extrabold uppercase tracking-wider text-indigo-500">Invoice {{ $index + 1 }}</p>
                            <p class="mt-2 text-base font-extrabold text-indigo-900 dark:text-indigo-200">{{ $date->format('d M Y') }}</p>
                            <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-300">{{ $date->format('l') }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Recurring Items</h4>
            <div class="mt-4 overflow-x-auto">
                <table>
                    <thead><tr><th>Item</th><th>Description</th><th>Quantity</th><th>Price</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($recurringInvoice->items as $item)
                            <tr>
                                <td class="font-semibold text-slate-950 dark:text-white">{{ $item->item_name }}</td>
                                <td>{{ strip_tags($item->description ?? '') ?: 'N/A' }}</td>
                                <td>{{ number_format($item->quantity, 2) }}</td>
                                <td>RM {{ number_format($item->price, 2) }}</td>
                                <td class="text-right">RM {{ number_format($item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
