<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ isset($invoice) ? __('Create Recurring Invoice from Existing Invoice') : __('Create Recurring Invoice') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="section-title">{{ isset($invoice) ? 'Recurring Schedule for '.$invoice->invoice_number : 'New Recurring Invoice' }}</h3>
                <p class="section-subtitle">Choose a client, billing cycle, line items, discount, and tax using the same structure as invoices and quotations.</p>
            </div>
            <a href="{{ route('clients.create') }}" class="btn-secondary">Add New Client</a>
        </div>

        <form method="POST" action="{{ isset($invoice) ? route('recurring-invoices.store-from-invoice', $invoice) : route('recurring-invoices.store') }}" class="space-y-8">
            @csrf
            @include('recurring_invoices._form', ['recurringInvoice' => null, 'invoice' => $invoice ?? null, 'clients' => $clients])
        </form>
    </div>
</x-app-layout>
