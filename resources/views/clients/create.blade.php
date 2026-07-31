<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Create New Client') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div>
            <h3 class="section-title">Client Profile</h3>
            <p class="section-subtitle">Capture billing, identity, address, and e-invoice-ready details for a professional invoice record.</p>
        </div>

        <form method="POST" action="{{ route('clients.store') }}" class="space-y-8">
            @csrf
            @include('clients.partials.myinvois-assistant')
            @include('clients.partials.form', ['client' => null])
        </form>
    </div>
</x-app-layout>
