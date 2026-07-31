<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">{{ __('My Invoices') }}</h2>
    </x-slot>

    <div class="space-y-6">
        <div>
            <h3 class="section-title">Your Invoices</h3>
            <p class="section-subtitle">Search, filter, export, view, and download your invoices.</p>
        </div>

        <x-filters-toolbar
            :action="route('invoices.customer_index')"
            search-placeholder="Search invoice number or status..."
            export-route="invoices.customer_export"
            :filters="[
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'placeholder' => 'All statuses', 'options' => ['pending' => 'Pending', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid', 'overdue' => 'Overdue']],
                ['name' => 'from', 'label' => 'From', 'type' => 'date'],
                ['name' => 'to', 'label' => 'To', 'type' => 'date'],
                ['name' => 'sort', 'label' => 'Sort', 'type' => 'select', 'placeholder' => 'Default', 'options' => ['date' => 'Invoice Date', 'due_date' => 'Due Date', 'total_amount' => 'Amount', 'status' => 'Status']],
            ]"
        />

        @if ($invoices->isEmpty())
            <x-empty-state title="No invoices found" message="No invoices matched your current search or filters." />
        @else
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td class="font-semibold text-slate-950 dark:text-white">{{ $invoice->invoice_number ?? 'N/A' }}</td>
                                <td>RM {{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                                <td>{{ Str::title(str_replace('_', ' ', $invoice->status ?? 'Draft')) }}</td>
                                <td>{{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : 'N/A' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('invoices.customer_show', $invoice) }}" class="mr-3">View</a>
                                    <a href="{{ route('invoices.customer_download', $invoice) }}">Download</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $invoices->links() }}</div>
        @endif
    </div>
</x-app-layout>
