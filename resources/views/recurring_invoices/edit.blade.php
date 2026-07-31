<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">{{ __('Edit Recurring Invoice') }}</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="section-title">{{ $recurringInvoice->invoice_prefix ?: 'Recurring Invoice' }}</h3>
                <p class="section-subtitle">Update the client, schedule, line items, discounts, taxes, and active status.</p>
            </div>
            <a href="{{ route('clients.create') }}" class="btn-secondary">Add New Client</a>
        </div>

        <form method="POST" action="{{ route('recurring-invoices.update', $recurringInvoice) }}" class="space-y-8">
            @csrf
            @method('PUT')
            @include('recurring_invoices._form', ['recurringInvoice' => $recurringInvoice, 'clients' => $clients])
        </form>
    </div>
</x-app-layout>
