<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Quotation Details') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <section class="card hover:translate-y-0">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">Quotation</p>
                    <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950 dark:text-white">
                        #{{ $quotation->quote_number ?? 'N/A' }}
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Review the quotation details, download a PDF, or continue it into an invoice.
                    </p>
                </div>

                <div class="document-action-row lg:max-w-2xl lg:justify-end">
                    <a href="{{ route('quotations.download', $quotation) }}" class="btn-success">Download PDF</a>

                    @unless($quotation->isLocked())
                        <a href="{{ route('invoices.create_from_quotation', $quotation->id) }}" class="btn-primary">Convert to Invoice</a>
                        <a href="{{ route('quotations.edit', $quotation) }}" class="btn-secondary">Edit</a>
                    @else
                        <span class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-amber-100 px-4 py-3 text-xs font-bold uppercase tracking-wide text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                            🔒 Converted · locked
                        </span>
                    @endunless

                    <a href="{{ route('quotations.index') }}" class="btn-muted">Back to List</a>
                </div>
            </div>
        </section>

        <section class="card hover:translate-y-0">
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60">
                    <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Client</p>
                    <p class="mt-2 text-lg font-bold text-slate-950 dark:text-white">{{ $quotation->client?->name ?? 'N/A' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60">
                    <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Amount</p>
                    <p class="mt-2 text-lg font-bold text-slate-950 dark:text-white">RM {{ number_format($quotation->total_amount ?? 0, 2) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60">
                    <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</p>
                    <p class="mt-2 text-lg font-bold capitalize text-slate-950 dark:text-white">{{ Str::title(str_replace('_', ' ', $quotation->status ?? 'N/A')) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60">
                    <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Issue Date</p>
                    <p class="mt-2 text-lg font-bold text-slate-950 dark:text-white">{{ $quotation->date?->format('Y-m-d') ?? 'N/A' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60">
                    <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Due Date</p>
                    <p class="mt-2 text-lg font-bold text-slate-950 dark:text-white">{{ $quotation->expiry_date?->format('Y-m-d') ?? 'N/A' }}</p>
                </div>
            </div>
        </section>

        <section class="card hover:translate-y-0">
            <div class="mb-4">
                <h3 class="text-lg font-bold text-slate-950 dark:text-white">Quotation Items</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Item descriptions retain their formatting, lists, links, and emphasis.</p>
            </div>

            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotation->items as $item)
                            <tr>
                                <td class="font-semibold text-slate-950 dark:text-white">{{ $item->item_name }}</td>
                                <td>
                                    @if($item->description)
                                        <div class="document-rich-text max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">{!! $item->description !!}</div>
                                    @else
                                        <span class="text-slate-400">No description</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right">RM {{ number_format($item->price, 2) }}</td>
                                <td class="text-right font-semibold">RM {{ number_format($item->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-slate-500">No quotation items found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>