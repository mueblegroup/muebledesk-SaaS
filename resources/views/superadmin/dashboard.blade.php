<x-superadmin-layout>
    <x-slot name="title">Overview</x-slot>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-violet-600">Control plane</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950 dark:text-white">SaaS platform overview</h1>
        </div>
    </x-slot>

    <div class="space-y-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <p class="text-sm text-slate-500 dark:text-slate-400">Companies, subscriptions, seat usage and recurring revenue.</p>
            <a href="{{ route('superadmin.plans.index') }}" class="btn-primary">Manage seat plans</a>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Companies', $companyCount], ['Users', $userCount], ['Active subscriptions', $activeSubscriptionCount], ['MRR', 'RM '.number_format($monthlyRecurringRevenue, 2)]] as [$label, $value])
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-black text-slate-950 dark:text-white">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800">
                <h2 class="font-extrabold text-slate-950 dark:text-white">Latest companies</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="px-6 py-3">Company</th><th class="px-6 py-3">Owner</th><th class="px-6 py-3">Plan</th><th class="px-6 py-3">Seats</th><th class="px-6 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($companies as $company)
                            <tr><td class="px-6 py-4 font-bold">{{ $company->name }}</td><td class="px-6 py-4">{{ $company->owners->first()?->email ?? '—' }}</td><td class="px-6 py-4">{{ $company->subscription?->plan?->name ?? 'No plan' }}</td><td class="px-6 py-4">{{ $company->seatsUsed() }} / {{ $company->subscription?->seats ?? '—' }}</td><td class="px-6 py-4 capitalize">{{ $company->subscription?->status ?? 'unsubscribed' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">No companies yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-superadmin-layout>
