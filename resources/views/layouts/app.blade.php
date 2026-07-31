<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ sidebarOpen: false, theme: window.getTheme ? window.getTheme() : 'system' }" x-init="window.addEventListener('theme-changed', event => theme = event.detail.theme)">
<head>
    <script>
        (function () {
            const theme = localStorage.getItem('theme') || 'system';
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && prefersDark));
        })();
    </script>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Mueble Invoice Management') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body class="font-sans antialiased soft-gradient">
    @php
        $user = Auth::user();
        $roleLabel = $user?->role?->value ? ucfirst($user->role->value) : 'User';
        $canManageCompany = $user?->isAdmin() || $user?->isEmployee();
        $navGroups = [
            ['key' => 'main', 'label' => 'Workspace', 'icon' => '🏠', 'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'show' => true, 'icon' => '🏠'],
            ]],
            ['key' => 'sales', 'label' => 'Sales & Billing', 'icon' => '💼', 'items' => [
                ['label' => 'Clients', 'route' => 'clients.index', 'active' => 'clients.*', 'show' => $canManageCompany, 'icon' => '🤝'],
                ['label' => 'Quotations', 'route' => 'quotations.index', 'active' => 'quotations.*', 'show' => $canManageCompany, 'icon' => '📝'],
                ['label' => 'Invoices', 'route' => 'invoices.index', 'active' => 'invoices.*', 'show' => $canManageCompany, 'icon' => '🧾'],
                ['label' => 'e-Invoices', 'route' => 'einvoices.index', 'active' => 'einvoices.*', 'show' => $canManageCompany, 'icon' => '🧿'],
                ['label' => 'Recurring Invoices', 'route' => 'recurring-invoices.index', 'active' => 'recurring-invoices.*', 'show' => $canManageCompany, 'icon' => '🔁'],
                ['label' => 'Payments', 'route' => 'payments.index', 'active' => 'payments.*', 'show' => $canManageCompany, 'icon' => '💳'],
            ]],
            ['key' => 'finance', 'label' => 'Finance', 'icon' => '📊', 'items' => [
                ['label' => 'Expenses', 'route' => 'expenses.index', 'active' => 'expenses.index', 'show' => $canManageCompany, 'icon' => '💸'],
                ['label' => 'Profit & Loss', 'route' => 'expenses.profit_loss', 'active' => 'expenses.profit_loss', 'show' => $canManageCompany, 'icon' => '📈'],
            ]],
            ['key' => 'admin', 'label' => 'Administration', 'icon' => '🛡️', 'items' => [
                ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'show' => $user?->isAdmin(), 'icon' => '👥'],
                ['label' => 'Settings', 'route' => 'admin.setting.index', 'active' => 'admin.setting.*', 'show' => $user?->isAdmin(), 'icon' => '⚙️'],
                ['label' => 'API Keys', 'route' => 'admin.api-keys.index', 'active' => 'admin.api-keys.*', 'show' => $user?->isAdmin(), 'icon' => '🔑'],
                ['label' => 'Activity Logs', 'route' => 'admin.activity-logs.index', 'active' => 'admin.activity-logs.*', 'show' => $user?->isAdmin(), 'icon' => '🧾'],
            ]],
            ['key' => 'customer', 'label' => 'Customer Portal', 'icon' => '📄', 'items' => [
                ['label' => 'My Invoices', 'route' => 'invoices.customer_index', 'active' => 'invoices.customer_*', 'show' => $user?->isCustomer(), 'icon' => '📄'],
                ['label' => 'My e-Invoice Profile', 'route' => 'customer.einvoice-profile.edit', 'active' => 'customer.einvoice-profile.*', 'show' => $user?->isCustomer(), 'icon' => '🪪'],
            ]],
            ['key' => 'account', 'label' => 'Account', 'icon' => '👤', 'items' => [
                ['label' => 'System Guide', 'route' => 'system-guide.index', 'active' => 'system-guide.*', 'show' => true, 'icon' => '📚'],
                ['label' => 'Profile & Security', 'route' => 'profile.edit', 'active' => 'profile.*', 'show' => true, 'icon' => '👤'],
            ]],
        ];
    @endphp

    <div class="min-h-screen lg:flex">
        <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>
        <aside class="fixed inset-y-0 left-0 z-50 w-80 max-w-[86vw] -translate-x-full border-r border-white/70 bg-white/90 p-4 shadow-2xl shadow-slate-950/10 backdrop-blur-xl transition duration-300 dark:border-slate-800 dark:bg-slate-950/90 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shadow-none" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="flex h-full flex-col">
                <div class="mb-4 flex items-center justify-between rounded-3xl border border-slate-200 bg-white p-4 text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-white">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-600 text-xl font-black text-white shadow-lg shadow-indigo-500/20">M</span>
                        <span><span class="block text-sm font-extrabold leading-tight">{{ config('app.name', 'Mueble Desk') }}</span><span class="block text-xs text-slate-500 dark:text-slate-400">Modern workspace</span></span>
                    </a>
                    <button type="button" class="rounded-2xl p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-950 lg:hidden dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" @click="sidebarOpen = false" aria-label="Close navigation">✕</button>
                </div>

                <nav class="flex-1 space-y-3 overflow-y-auto pr-1">
                    @foreach ($navGroups as $group)
                        @php
                            $visibleItems = collect($group['items'])->filter(fn ($item) => $item['show'] && Route::has($item['route']))->values();
                            $groupActive = $visibleItems->contains(fn ($item) => request()->routeIs($item['active']));
                        @endphp
                        @if ($visibleItems->isNotEmpty())
                            <div x-data="{ open: {{ $groupActive || $group['key'] === 'main' ? 'true' : 'false' }} }" class="rounded-3xl border border-slate-200 bg-white/70 p-2 dark:border-slate-800 dark:bg-slate-900/60">
                                <button type="button" @click="open = !open" class="flex w-full items-center justify-between rounded-2xl px-3 py-2 text-left text-xs font-black uppercase tracking-wide text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                    <span class="flex items-center gap-2"><span>{{ $group['icon'] }}</span><span>{{ $group['label'] }}</span></span>
                                    <svg class="h-4 w-4 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-show="open" class="mt-1 space-y-1">
                                    @foreach ($visibleItems as $item)
                                        <x-nav-link :href="route($item['route'])" :active="request()->routeIs($item['active'])"><span>{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span></x-nav-link>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </nav>

                <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-600 text-sm font-bold text-white">{{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}</div>
                        <div class="min-w-0"><p class="truncate text-sm font-bold text-slate-950 dark:text-white">{{ $user?->name }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ $roleLabel }}</p></div>
                    </div>
                </div>
            </div>
        </aside>
        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 border-b border-white/70 bg-white/80 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/80">
                <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 bg-white p-3 text-slate-700 shadow-sm lg:hidden dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200" @click="sidebarOpen = true" aria-label="Open navigation"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg></button>
                        <div>@if (isset($header)){{ $header }}@else<h1 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Dashboard</h1><p class="hidden text-sm text-slate-500 dark:text-slate-400 sm:block">Your workspace overview</p>@endif</div>
                    </div>
                    <div class="flex min-w-0 flex-1 items-center justify-end gap-3">
                        <form action="{{ route('search.index') }}" method="GET" class="hidden w-full max-w-md lg:block"><input name="q" value="{{ request('q') }}" type="search" placeholder="Search clients, invoices, quotations..." class="block w-full rounded-2xl border-slate-200 bg-white/90 py-2.5 text-sm dark:border-slate-800 dark:bg-slate-900"></form>
                        <button type="button" class="hidden rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 sm:inline-flex" @click="window.setTheme(theme === 'dark' ? 'light' : 'dark')"><span x-text="theme === 'dark' ? '☀️ Light' : '🌙 Dark'"></span></button>
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger"><button class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-xs font-bold text-white">{{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}</span><span class="hidden text-left sm:block"><span class="block leading-tight">{{ $user?->name }}</span><span class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ $roleLabel }}</span></span><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></button></x-slot>
                            <x-slot name="content"><x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link><button type="button" onclick="window.setTheme(window.getTheme && window.getTheme() === 'dark' ? 'light' : 'dark')" class="block w-full px-4 py-2 text-start text-sm leading-5 text-slate-700 transition duration-150 ease-in-out hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:bg-slate-800">Toggle theme</button><form method="POST" action="{{ route('logout') }}">@csrf<x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Log Out') }}</x-dropdown-link></form></x-slot>
                        </x-dropdown>
                    </div>
                </div>
            </header>
            <main class="min-h-[calc(100vh-73px)] w-full px-4 py-6 sm:px-6 lg:px-8">
                <div class="fixed right-4 top-24 z-50 w-full max-w-sm space-y-3">
                    @foreach (['success' => 'emerald', 'status' => 'emerald', 'error' => 'red', 'warning' => 'amber'] as $key => $color)
                        @if (session($key))
                            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="rounded-2xl border bg-white px-4 py-3 text-sm font-semibold shadow-xl dark:bg-slate-900 {{ $color === 'emerald' ? 'border-emerald-200 text-emerald-700 dark:border-emerald-900 dark:text-emerald-300' : ($color === 'red' ? 'border-red-200 text-red-700 dark:border-red-900 dark:text-red-300' : 'border-amber-200 text-amber-700 dark:border-amber-900 dark:text-amber-300') }}"><div class="flex items-start justify-between gap-3"><span>{{ session($key) }}</span><button type="button" @click="show = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">✕</button></div></div>
                        @endif
                    @endforeach
                    @if ($errors->any())<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 7000)" x-transition class="rounded-2xl border border-red-200 bg-white px-4 py-3 text-sm font-semibold text-red-700 shadow-xl dark:border-red-900 dark:bg-slate-900 dark:text-red-300"><div class="flex items-start justify-between gap-3"><span>Please check the form errors and try again.</span><button type="button" @click="show = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">✕</button></div></div>@endif
                </div>
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
