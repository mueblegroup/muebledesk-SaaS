<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Edit Quotation') }} #{{ $quotation->quote_number ?? '' }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div>
            <h3 class="section-title">Quotation Details</h3>
            <p class="section-subtitle">Update the client, dates, items, discounts, tax, and quotation status.</p>
        </div>

        <form method="POST" action="{{ route('quotations.update', $quotation) }}" class="space-y-8">
            @csrf
            @method('PATCH')

            @include('quotations._form', ['quotation' => $quotation, 'clients' => $clients])
        </form>
    </div>
</x-app-layout>
