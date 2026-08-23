<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">{{ __('Invoices') }}</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="section-title">All Invoices</h3>
                <p class="section-subtitle">Search, filter, export, share, and manage invoices.</p>
            </div>
            <a href="{{ route('invoices.create') }}" class="btn-primary">Create New Invoice</a>
        </div>

        <x-filters-toolbar
            :action="route('invoices.index')"
            search-placeholder="Search invoice number, client, status..."
            export-route="invoices.export"
            :filters="[
                ['name' => 'client_id', 'label' => 'Client', 'type' => 'select', 'placeholder' => 'All clients', 'options' => $clients->pluck('name', 'id')->toArray()],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'placeholder' => 'All statuses', 'options' => ['pending' => 'Pending', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid', 'overdue' => 'Overdue']],
                ['name' => 'from', 'label' => 'From', 'type' => 'date'],
                ['name' => 'to', 'label' => 'To', 'type' => 'date'],
            ]"
        />

        @if ($invoices->isEmpty())
            <x-empty-state title="No invoices found" message="No invoices matched your current search or filters." :action-url="route('invoices.create')" action-label="Create Invoice" />
        @else
            <form method="POST" action="{{ route('invoices.bulk_destroy') }}" x-data="{ selected: [] }" onsubmit="return confirm('Delete selected invoices?');" class="space-y-4">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $invoices->total() }} invoice(s) found</p>
                    <button type="submit" x-show="selected.length" x-cloak class="rounded-2xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500">Delete Selected</button>
                </div>

                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('.invoice-checkbox')).map(el => el.value) : []"></th>
                                <th>Invoice #</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th>e-Invoice</th>
                                <th>Due Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                @php
                                    $eInvoice = $invoice->eInvoice;
                                    $isFullyPaid = (float) $invoice->total_amount > 0
                                        && (float) $invoice->amount_paid >= (float) $invoice->total_amount;
                                    $canRetryEInvoice = $eInvoice && in_array($eInvoice->status, ['invalid', 'rejected', 'failed'], true);
                                    $shareUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('shared.invoice', now()->addDays(30), ['invoice' => $invoice]);
                                    $shareMessage = 'Invoice #'.($invoice->invoice_number ?? $invoice->id).' from '.(app('currentCompany')->name ?? config('app.name')).'. Total: RM '.number_format((float) $invoice->total_amount, 2).'.';
                                @endphp
                                <tr>
                                    <td>@unless($invoice->isLocked())<input name="ids[]" value="{{ $invoice->id }}" type="checkbox" class="invoice-checkbox" x-model="selected">@else <span title="Locked">🔒</span> @endunless</td>
                                    <td class="font-semibold text-slate-950 dark:text-white">{{ $invoice->invoice_number ?? 'N/A' }}</td>
                                    <td>{{ $invoice->client->name ?? 'Unknown Client' }}</td>
                                    <td>RM {{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                                    <td>RM {{ number_format($invoice->amount_paid ?? 0, 2) }}</td>
                                    <td>{{ Str::title(str_replace('_', ' ', $invoice->status)) }}</td>
                                    <td>
                                        @if ($eInvoice?->status === 'valid' && $eInvoice->myinvois_uuid && $eInvoice->long_id)
                                            <a href="{{ route('einvoices.preview', $invoice) }}" class="inline-flex rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-500">View QR</a>
                                        @elseif ($canRetryEInvoice)
                                            <a href="{{ route('einvoices.preview', $invoice) }}" class="inline-flex rounded-xl bg-amber-600 px-3 py-2 text-xs font-bold text-white hover:bg-amber-500">Retry e-Invoice</a>
                                        @elseif ($eInvoice)
                                            <a href="{{ route('einvoices.preview', $invoice) }}" class="inline-flex rounded-xl bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-600">{{ Str::title(str_replace('_', ' ', $eInvoice->status)) }}</a>
                                        @elseif ($isFullyPaid)
                                            <a href="{{ route('einvoices.preview', $invoice) }}" class="inline-flex rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-500">Create e-Invoice</a>
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">Available after full payment</span>
                                        @endif
                                    </td>
                                    <td>{{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : 'N/A' }}</td>
                                    <td class="text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-3">
                                            <a href="{{ route('invoices.show', $invoice) }}">View</a>
                                            <x-document-share
                                                :url="$shareUrl"
                                                :title="'Invoice #'.($invoice->invoice_number ?? $invoice->id)"
                                                :message="$shareMessage"
                                                :phone="$invoice->client?->phone"
                                                :email="$invoice->client?->billing_email ?: $invoice->client?->email"
                                                :country-code="$invoice->client?->country_code"
                                            />
                                            @unless($invoice->isLocked())
                                                <a href="{{ route('invoices.edit', $invoice) }}">Edit</a>
                                                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete this invoice?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                                </form>
                                            @else
                                                <span class="text-xs font-semibold text-amber-600">Locked</span>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            <div>{{ $invoices->links() }}</div>
        @endif
    </div>
</x-app-layout>
