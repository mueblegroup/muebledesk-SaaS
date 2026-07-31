<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Create New Quotation') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div>
            <h3 class="section-title">Quotation Details</h3>
            <p class="section-subtitle">Create quotations with the same guided layout used for invoices, including quick client creation, item lines, discounts, and optional tax.</p>
        </div>

        <form id="quick-client-quotation-form" method="POST" action="{{ route('clients.quick_store') }}">
            @csrf
        </form>

        <form method="POST" action="{{ route('quotations.store') }}" class="space-y-8">
            @csrf
            @include('quotations._form', ['quotation' => null, 'clients' => $clients])
        </form>
    </div>
</x-app-layout>
