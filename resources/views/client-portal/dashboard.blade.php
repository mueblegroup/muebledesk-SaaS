<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Client Portal</h2>
                <p class="mt-1 text-sm text-gray-600">Manage your companies, workspace access, plan and billing.</p>
            </div>
            <a href="{{ route('companies.create') }}" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                Add company
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($companies as $company)
                    @php
                        $workspaceHost = $company->slug.'.'.$rootDomain;
                    @endphp
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $company->name }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ $workspaceHost }}</p>
                            </div>
                            @if ($currentCompany?->is($company))
                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">Current</span>
                            @endif
                        </div>

                        <dl class="mt-5 space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Plan</dt>
                                <dd class="font-medium text-gray-900">Trial / not configured</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Role</dt>
                                <dd class="font-medium capitalize text-gray-900">{{ $company->pivot->role ?? 'member' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 grid gap-3">
                            <form method="POST" action="{{ route('companies.switch', $company) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                    Open invoicing workspace
                                </button>
                            </form>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" disabled class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-400">Plan & billing</button>
                                <button type="button" disabled class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-400">Team</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
