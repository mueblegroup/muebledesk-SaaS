<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Client Portal</h2>
                <p class="mt-1 text-sm text-gray-500">Manage companies, subscriptions, billing and account settings.</p>
            </div>

            <a href="{{ route('portal.companies.create') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Create company
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Your companies</h3>
                    <p class="mt-1 text-sm text-gray-500">Choose a company to open its invoicing workspace.</p>
                </div>

                <div class="divide-y divide-gray-200">
                    @forelse ($companies as $company)
                        <div class="flex items-center justify-between gap-4 p-6">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="font-semibold text-gray-900">{{ $company->name }}</h4>
                                    @if ($currentCompany?->is($company))
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Active</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $company->registration_number ?: 'Registration number not added' }}
                                </p>
                            </div>

                            <form method="POST" action="{{ route('portal.companies.switch', $company) }}">
                                @csrf
                                <button type="submit"
                                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Open workspace
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="p-10 text-center">
                            <h4 class="font-semibold text-gray-900">No company created yet</h4>
                            <p class="mt-2 text-sm text-gray-500">Create your first company to start invoicing.</p>
                            <a href="{{ route('portal.companies.create') }}"
                               class="mt-5 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                Create your first company
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Subscription</h3>
                    <p class="mt-2 text-sm text-gray-500">Plan selection, usage limits and upgrades will live here.</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Billing</h3>
                    <p class="mt-2 text-sm text-gray-500">Invoices, payment methods and billing history will live here.</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Account settings</h3>
                    <p class="mt-2 text-sm text-gray-500">Profile, security and portal preferences will live here.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
