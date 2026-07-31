<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">{{ __('Recurring Invoices') }}</h2>
    </x-slot>

    <div class="space-y-6" x-data="{ selected: [] }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="section-title">Recurring Invoices</h3>
                <p class="section-subtitle">Search, filter, export, and manage recurring invoice schedules.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('clients.create') }}" class="btn-secondary">Add Client</a>
                <a href="{{ route('recurring-invoices.create') }}" class="btn-primary">New Recurring Invoice</a>
            </div>
        </div>

        <x-filters-toolbar
            :action="route('recurring-invoices.index')"
            search-placeholder="Search client, item, amount, prefix, frequency..."
            export-route="recurring-invoices.export"
            :filters="[
                ['name' => 'client_id', 'label' => 'Client', 'type' => 'select', 'placeholder' => 'All clients', 'options' => $clients->pluck('name', 'id')->toArray()],
                ['name' => 'frequency', 'label' => 'Frequency', 'type' => 'select', 'placeholder' => 'All', 'options' => ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'custom' => 'Custom interval']],
                ['name' => 'is_active', 'label' => 'Status', 'type' => 'select', 'placeholder' => 'All', 'options' => ['1' => 'Active', '0' => 'Inactive']],
                ['name' => 'from', 'label' => 'From', 'type' => 'date'],
            ]"
        />

        @if ($recurringInvoices->isEmpty())
            <x-empty-state title="No recurring invoices found" message="No recurring invoice schedules matched your filters." :action-url="route('recurring-invoices.create')" action-label="Create Schedule" />
        @else
            <form id="recurring-bulk-delete" method="POST" action="{{ route('recurring-invoices.bulk_destroy') }}" onsubmit="return confirm('Delete selected recurring invoices?');">@csrf</form>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $recurringInvoices->total() }} recurring invoice(s) found</p>
                <button type="submit" form="recurring-bulk-delete" x-show="selected.length" x-cloak class="rounded-2xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500">Delete Selected</button>
            </div>

            <div class="overflow-x-auto">
                <table>
                    <thead><tr><th><input type="checkbox" @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('.recurring-checkbox')).map(el => el.value) : []"></th><th>Client</th><th>Total</th><th>Frequency</th><th>Upcoming Dates</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($recurringInvoices as $recurringInvoice)
                            @php($upcomingDates = $recurringInvoice->upcomingInvoiceDates(3))
                            <tr>
                                <td><input form="recurring-bulk-delete" name="ids[]" value="{{ $recurringInvoice->id }}" type="checkbox" class="recurring-checkbox" x-model="selected"></td>
                                <td class="font-semibold text-slate-950 dark:text-white">{{ $recurringInvoice->client->name ?? 'N/A' }}</td>
                                <td>RM {{ number_format($recurringInvoice->total_amount, 2) }}</td>
                                <td>{{ $recurringInvoice->frequencyLabel() }}</td>
                                <td>
                                    @if ($upcomingDates->isEmpty())
                                        <span class="text-sm text-slate-400">No upcoming dates</span>
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($upcomingDates as $date)
                                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">{{ $date->format('d M Y') }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td><span class="rounded-full px-3 py-1 text-xs font-bold {{ $recurringInvoice->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' }}">{{ $recurringInvoice->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-right"><div class="flex flex-wrap justify-end gap-3"><a href="{{ route('recurring-invoices.show', $recurringInvoice) }}">View</a><a href="{{ route('recurring-invoices.edit', $recurringInvoice) }}">Edit</a><form action="{{ route('recurring-invoices.toggle-active', $recurringInvoice) }}" method="POST" class="inline-block">@csrf<button type="submit">{{ $recurringInvoice->is_active ? 'Deactivate' : 'Activate' }}</button></form><form action="{{ route('recurring-invoices.destroy', $recurringInvoice) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete this recurring invoice?');">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:text-red-500 dark:text-red-400">Delete</button></form></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $recurringInvoices->links() }}</div>
        @endif
    </div>
</x-app-layout>
