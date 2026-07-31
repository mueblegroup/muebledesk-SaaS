<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Edit Client') }}: {{ $client->name }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div>
            <h3 class="section-title">Update Client Profile</h3>
            <p class="section-subtitle">Maintain billing identity, address, tax, and e-invoice details.</p>
        </div>

        <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-8">
            @csrf
            @method('PUT')
            @include('clients.partials.myinvois-assistant')
            @include('clients.partials.form', ['client' => $client])
        </form>
    </div>
</x-app-layout>
