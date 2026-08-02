<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        (() => {
            const saved = localStorage.getItem('muebledesk-theme') || 'system';
            const dark = saved === 'dark' || (saved === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Client Portal' }} · {{ config('app.name', 'MuebleDesk') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
@php
    $portalUser = auth()->user();
    $hasCompany = $portalUser?->companies()->exists() ?? false;
    $selectedCompany = $company ?? $portalUser?->currentCompany;
    $billingRoute = Route::has('client-portal.billing.show') ? 'client-portal.billing.show' : 'client-portal.billing.index';
@endphp
<div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-white/10 bg-slate-950 text-white shadow-2xl transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shadow-none" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
            <a href="{{ route('client-portal.dashboard') }}" class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg font-black">M</span>
                <span class="min-w-0"><span class="block truncate text-base font-extrabold">MuebleDesk</span><span class="block truncate text-xs text-slate-400">Client Portal</span></span>
            </a>
            <button type="button" class="rounded-xl p-2 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" @click="sidebarOpen = false">✕</button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
            <a href="{{ route('client-portal.dashboard') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('client-portal.dashboard') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10">⌂</span><span>Overview</span>
            </a>

            @if ($selectedCompany && Route::has($billingRoute))
                <a href="{{ route($billingRoute, $selectedCompany) }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('client-portal.billing.*') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10">◇</span><span>Plan & billing</span>
                </a>
            @endif

            @unless ($hasCompany)
                <a href="{{ route('companies.create') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('companies.create') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10">＋</span><span>Create company</span>
                </a>
            @endunless

            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('profile.*') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10">◎</span><span>Account settings</span>
            </a>
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="rounded-2xl bg-white/5 p-4">
                <p class="truncate text-sm font-extrabold">{{ $portalUser?->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ $portalUser?->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-bold hover:bg-white/15">Log out</button></form>
            </div>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/90">
            <div class="mx-auto flex h-20 max-w-[1600px] items-center gap-4 px-4 sm:px-6 lg:px-10">
                <button type="button" class="rounded-2xl border border-slate-200 bg-white p-3 text-slate-700 shadow-sm lg:hidden dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200" @click="sidebarOpen = true">☰</button>
                <div class="min-w-0 flex-1">@if (isset($header)){{ $header }}@else<h1 class="text-xl font-extrabold">{{ $title ?? 'Client Portal' }}</h1>@endif</div>
            </div>
        </header>

        <main class="min-h-[calc(100vh-5rem)]"><div class="mx-auto max-w-[1600px] px-4 py-7 sm:px-6 lg:px-10 lg:py-10">
            @if (session('success'))<div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif
            {{ $slot }}
        </div></main>
    </div>
</div>
</body>
</html>
