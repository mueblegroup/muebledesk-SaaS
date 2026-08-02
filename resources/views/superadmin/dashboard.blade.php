<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-950 dark:text-white">SaaS Superadmin</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Platform companies, subscriptions, revenue and plans.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            @foreach ([['Companies', $companyCount], ['Users', $userCount], ['Active subscriptions', $activeSubscriptionCount], ['MRR', 'RM '.number_format($monthlyRecurringRevenue, 2)]] as [$label, $value])
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-black text-slate-950 dark:text-white">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end">
            <a href="{{ route('superadmin.plans.index') }}" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">Manage seat plans</a>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-extrabold">Latest companies</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="px-6 py-3">Company</th><th class="px-6 py-3">Owner</th><th class="px-6 py-3">Plan</th><th class="px-6 py-3">Seats</th><th class="px-6 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($companies as $company)
                            <tr><td class="px-6 py-4 font-bold">{{ $company->name }}</td><td class="px-6 py-4">{{ $company->owners->first()?->email ?? '—' }}</td><td class="px-6 py-4">{{ $company->subscription?->plan?->name ?? 'No plan' }}</td><td class="px-6 py-4">{{ $company->subscription?->seats ?? '—' }}</td><td class="px-6 py-4 capitalize">{{ $company->subscription?->status ?? 'unsubscribed' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
