<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Superadmin · {{ config('app.name', 'MuebleDesk') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900">
<div class="min-h-screen lg:flex">
    <aside class="w-full bg-slate-950 text-white lg:sticky lg:top-0 lg:h-screen lg:w-72">
        <div class="border-b border-white/10 px-6 py-5"><div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-500 text-lg font-black">M</span><div><p class="font-extrabold">MuebleDesk</p><p class="text-xs text-slate-400">Platform Superadmin</p></div></div></div>
        <nav class="space-y-2 p-4"><a href="{{ route('superadmin.dashboard') }}" class="flex rounded-2xl bg-white/10 px-4 py-3 text-sm font-bold">Platform overview</a><a href="{{ route('superadmin.plans.index') }}" class="flex rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10">Seat plans</a></nav>
        <div class="border-t border-white/10 p-4 lg:absolute lg:bottom-0 lg:w-72"><div class="rounded-2xl bg-white/5 p-4"><p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p><form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold">Log out</button></form></div></div>
    </aside>
    <main class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl space-y-7">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-[0.2em] text-violet-600">Control plane</p><h1 class="mt-2 text-3xl font-black">SaaS platform overview</h1><p class="mt-1 text-sm text-slate-500">Companies, subscriptions, seat usage and recurring revenue.</p></div><a href="{{ route('superadmin.plans.index') }}" class="rounded-2xl bg-violet-600 px-5 py-3 text-center text-sm font-bold text-white">Manage seat plans</a></header>
            <section class="grid gap-4 md:grid-cols-4">@foreach ([['Companies', $companyCount], ['Users', $userCount], ['Active subscriptions', $activeSubscriptionCount], ['MRR', 'RM '.number_format($monthlyRecurringRevenue, 2)]] as [$label, $value])<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-sm font-semibold text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-black">{{ $value }}</p></div>@endforeach</section>
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 px-6 py-4"><h2 class="font-extrabold">Latest companies</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead><tr class="text-left text-xs uppercase text-slate-500"><th class="px-6 py-3">Company</th><th class="px-6 py-3">Owner</th><th class="px-6 py-3">Plan</th><th class="px-6 py-3">Seats</th><th class="px-6 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ($companies as $company)<tr><td class="px-6 py-4 font-bold">{{ $company->name }}</td><td class="px-6 py-4">{{ $company->owners->first()?->email ?? '—' }}</td><td class="px-6 py-4">{{ $company->subscription?->plan?->name ?? 'No plan' }}</td><td class="px-6 py-4">{{ $company->seatsUsed() }} / {{ $company->subscription?->seats ?? '—' }}</td><td class="px-6 py-4 capitalize">{{ $company->subscription?->status ?? 'unsubscribed' }}</td></tr>@endforeach</tbody></table></div></section>
        </div>
    </main>
</div>
</body>
</html>
