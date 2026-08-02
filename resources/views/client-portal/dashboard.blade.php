<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Client Portal · {{ config('app.name', 'MuebleDesk') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    @php
        $user = auth()->user();
        $companyCount = $companies->count();
        $activeCompany = $currentCompany ?: $companies->first();
    @endphp

    <div x-data="{ mobileMenu: false }" class="min-h-screen lg:flex">
        <div x-show="mobileMenu" x-cloak class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="mobileMenu = false"></div>

        <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-slate-950 text-white transition lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" :class="mobileMenu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="border-b border-white/10 px-6 py-5">
                <a href="{{ route('client-portal.dashboard') }}" class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500 text-lg font-extrabold">M</span>
                    <span><span class="block font-extrabold">MuebleDesk</span><span class="block text-xs text-slate-400">SaaS Client Portal</span></span>
                </a>
            </div>

            <nav class="flex-1 space-y-2 px-4 py-6">
                <a href="{{ route('client-portal.dashboard') }}" class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 text-sm font-bold"><span>▦</span>Overview</a>
                <a href="{{ route('companies.create') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 hover:text-white"><span>＋</span>Create company</a>
                @if ($activeCompany)
                    <a href="{{ route('client-portal.billing.index', $activeCompany) }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 hover:text-white"><span>💳</span>Plans & billing</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 hover:text-white"><span>◎</span>Account settings</a>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="rounded-2xl bg-white/5 p-4">
                    <p class="truncate text-sm font-bold">{{ $user?->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ $user?->email }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold hover:bg-white/15">Log out</button></form>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-xl">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-10">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-xl border border-slate-200 bg-white p-2.5 lg:hidden" @click="mobileMenu = true">☰</button>
                        <div><h1 class="text-xl font-extrabold">Client Portal</h1><p class="text-sm text-slate-500">Companies, seats, plans and billing</p></div>
                    </div>
                    <a href="{{ route('companies.create') }}" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">+ Create company</a>
                </div>
            </header>

            <main class="px-4 py-8 sm:px-6 lg:px-10">
                <div class="mx-auto max-w-7xl space-y-8">
                    @foreach (['success' => 'emerald', 'error' => 'red'] as $key => $color)
                        @if (session($key))
                            <div class="rounded-2xl border px-5 py-4 text-sm font-semibold {{ $color === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' }}">{{ session($key) }}</div>
                        @endif
                    @endforeach

                    <section class="rounded-3xl bg-slate-950 px-7 py-8 text-white shadow-2xl sm:px-10">
                        <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                            <div><span class="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-bold text-indigo-200">MuebleDesk SaaS</span><h2 class="mt-5 text-3xl font-extrabold sm:text-4xl">Welcome back, {{ $user?->name }}.</h2><p class="mt-3 max-w-2xl text-slate-300">Manage subscriptions centrally, then open each company’s isolated invoicing workspace.</p></div>
                            <div class="grid grid-cols-2 gap-3"><div class="rounded-2xl bg-white/10 p-4"><p class="text-2xl font-extrabold">{{ $companyCount }}</p><p class="text-xs text-slate-400">Companies</p></div><div class="rounded-2xl bg-white/10 p-4"><p class="text-2xl font-extrabold">{{ $companies->sum(fn ($company) => $company->subscription?->seats ?? 0) }}</p><p class="text-xs text-slate-400">Purchased seats</p></div></div>
                        </div>
                    </section>

                    <section>
                        <div class="mb-5"><h2 class="text-2xl font-extrabold">Your companies</h2><p class="text-sm text-slate-500">Workspace access and platform subscription status.</p></div>
                        <div class="grid gap-6 xl:grid-cols-2">
                            @foreach ($companies as $company)
                                @php($subscription = $company->subscription)
                                <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                                    <div class="border-b border-slate-100 p-6">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex items-center gap-4"><div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-xl font-extrabold text-white">{{ strtoupper(substr($company->name, 0, 1)) }}</div><div><h3 class="text-xl font-extrabold">{{ $company->name }}</h3><p class="text-sm text-slate-500">{{ $scheme }}://{{ $company->slug }}.{{ $rootDomain }}</p></div></div>
                                            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold capitalize text-indigo-700">{{ $company->pivot->role ?? 'member' }}</span>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 p-6 sm:grid-cols-3">
                                        <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase text-slate-400">Plan</p><p class="mt-2 font-extrabold">{{ $subscription?->plan?->name ?? 'No plan' }}</p></div>
                                        <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase text-slate-400">Seats</p><p class="mt-2 font-extrabold">{{ $company->seatsUsed() }} / {{ $subscription?->seats ?? '—' }}</p></div>
                                        <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase text-slate-400">Status</p><p class="mt-2 font-extrabold capitalize {{ $subscription?->isActive() ? 'text-emerald-600' : 'text-amber-600' }}">{{ $subscription?->status ?? 'unsubscribed' }}</p></div>
                                    </div>

                                    <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 p-5 sm:flex-row">
                                        <form method="POST" action="{{ route('companies.switch', $company) }}" class="flex-1">@csrf<button class="w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-extrabold text-white">Open invoicing workspace</button></form>
                                        <a href="{{ route('client-portal.billing.index', $company) }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-bold text-slate-700">Plan & billing</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
