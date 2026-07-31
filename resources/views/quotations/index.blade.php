<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">{{ __('Quotations') }}</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="section-title">All Quotations</h3>
                <p class="section-subtitle">Search, filter, export, and manage quotations.</p>
            </div>
            <a href="{{ route('quotations.create') }}" class="btn-primary">{{ __('Create New Quotation') }}</a>
        </div>

        <x-filters-toolbar
            :action="route('quotations.index')"
            search-placeholder="Search quote number, client, status..."
            export-route="quotations.export"
            :filters="[
                ['name' => 'client_id', 'label' => 'Client', 'type' => 'select', 'placeholder' => 'All clients', 'options' => $clients->pluck('name', 'id')->toArray()],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'placeholder' => 'All statuses', 'options' => ['draft' => 'Draft', 'sent' => 'Sent', 'approved' => 'Approved', 'rejected' => 'Rejected', 'converted_to_invoice' => 'Converted']],
                ['name' => 'from', 'label' => 'From', 'type' => 'date'],
                ['name' => 'to', 'label' => 'To', 'type' => 'date'],
            ]"
        />

        @if ($quotations->isEmpty())
            <x-empty-state title="No quotations found" message="No quotations matched your current search or filters." :action-url="route('quotations.create')" action-label="Create Quotation" />
        @else
            <form method="POST" action="{{ route('quotations.bulk_destroy') }}" x-data="{ selected: [] }" onsubmit="return confirm('Delete selected quotations?');" class="space-y-4">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $quotations->total() }} quotation(s) found</p>
                    <button type="submit" x-show="selected.length" x-cloak class="rounded-2xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500">Delete Selected</button>
                </div>

                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('.quotation-checkbox')).map(el => el.value) : []"></th>
                                <th>Quotation #</th>
                                <th>Client Name</th>
                                <th>Total Amount</th>
                                <th>Quotation Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotations as $quotation)
                                <tr>
                                    <td>@unless($quotation->isLocked())<input name="ids[]" value="{{ $quotation->id }}" type="checkbox" class="quotation-checkbox" x-model="selected">@else <span title="Locked">🔒</span> @endunless</td>
                                    <td class="font-semibold text-slate-950 dark:text-white">{{ $quotation->quote_number ?? 'N/A' }}</td>
                                    <td>{{ $quotation->client->name ?? 'N/A' }}</td>
                                    <td>RM {{ number_format($quotation->total_amount ?? 0, 2) }}</td>
                                    <td>{{ $quotation->date ? $quotation->date->format('Y-m-d') : 'N/A' }}</td>
                                    <td>{{ $quotation->expiry_date ? $quotation->expiry_date->format('Y-m-d') : 'N/A' }}</td>
                                    <td>{{ Str::title(str_replace('_', ' ', $quotation->status ?? 'N/A')) }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('quotations.show', $quotation) }}" class="mr-3">View</a>
                                        @unless($quotation->isLocked())<a href="{{ route('quotations.edit', $quotation) }}" class="mr-3">Edit</a>
                                        <form action="{{ route('quotations.destroy', $quotation) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this quotation?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                        </form>
                                        @else<span class="text-xs font-semibold text-amber-600">Converted · locked</span>@endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            <div>{{ $quotations->links() }}</div>
        @endif
    </div>
</x-app-layout>
