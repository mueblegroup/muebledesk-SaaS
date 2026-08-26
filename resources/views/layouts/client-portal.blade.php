<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        (() => {
            const saved = localStorage.getItem('muebledesk-theme') || 'system';
            const dark = saved === 'dark' || (saved === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            window.muebleTheme = {
                get: () => localStorage.getItem('muebledesk-theme') || 'system',
                set: (theme) => {
                    localStorage.setItem('muebledesk-theme', theme);
                    const enabled = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', enabled);
                    window.dispatchEvent(new CustomEvent('mueble-theme-changed', { detail: theme }));
                },
            };
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MuebleDesk') }} · Client Portal</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
@php
    $user = auth()->user();
    $companies = $user?->companies()
        ->wherePivotIn('role', ['owner', 'admin'])
        ->with('subscription.plan')
        ->orderBy('name')
        ->get() ?? collect();
    $currentCompany = $companies->firstWhere('id', $user?->current_company_id) ?: $companies->first();
    $billingCompany = $currentCompany;
@endphp
<div x-data="{ sidebarOpen: false, theme: window.muebleTheme.get() }" @mueble-theme-changed.window="theme = $event.detail" class="min-h-screen lg:flex">
    <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-white/10 bg-slate-950 text-white shadow-2xl transition duration-300 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="border-b border-white/10 px-5 py-5">
            <a href="{{ route('client-portal.dashboard') }}" class="flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-500 text-xl font-black text-white shadow-lg shadow-indigo-500/25">M</span>
                <span><span class="block text-base font-extrabold tracking-tight">MuebleDesk</span><span class="block text-xs font-medium text-slate-400">Business control centre</span></span>
            </a>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
            @php
                $links = [
                    ['label' => 'Overview', 'route' => 'client-portal.dashboard', 'active' => 'client-portal.dashboard', 'icon' => '▦'],
                    ['label' => 'Create company', 'route' => 'companies.create', 'active' => 'companies.create', 'icon' => '+'],
                    ['label' => 'Profile & security', 'route' => 'profile.edit', 'active' => 'profile.*', 'icon' => '◎'],
                ];
            @endphp
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs($link['active']) ? 'bg-white text-slate-950 shadow-lg shadow-black/10' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl {{ request()->routeIs($link['active']) ? 'bg-indigo-100 text-indigo-700' : 'bg-white/10' }}">{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
            @if ($billingCompany && Route::has('client-portal.billing.index'))
                <a href="{{ route('client-portal.billing.index', $billingCompany) }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('client-portal.billing.*') ? 'bg-white text-slate-950 shadow-lg shadow-black/10' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl {{ request()->routeIs('client-portal.billing.*') ? 'bg-indigo-100 text-indigo-700' : 'bg-white/10' }}">◇</span>
                    <span>Plans & billing</span>
                </a>
            @endif
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="mb-3 grid grid-cols-3 gap-2 rounded-2xl bg-white/5 p-2 text-xs font-bold">
                <button type="button" @click="window.muebleTheme.set('light')" :class="theme === 'light' ? 'bg-white text-slate-950' : 'text-slate-400 hover:text-white'" class="rounded-xl px-2 py-2">Light</button>
                <button type="button" @click="window.muebleTheme.set('dark')" :class="theme === 'dark' ? 'bg-white text-slate-950' : 'text-slate-400 hover:text-white'" class="rounded-xl px-2 py-2">Dark</button>
                <button type="button" @click="window.muebleTheme.set('system')" :class="theme === 'system' ? 'bg-white text-slate-950' : 'text-slate-400 hover:text-white'" class="rounded-xl px-2 py-2">System</button>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500 text-sm font-black">{{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}</span>
                    <div class="min-w-0"><p class="truncate text-sm font-extrabold">{{ $user?->name }}</p><p class="truncate text-xs text-slate-400">{{ $user?->email }}</p></div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-bold transition hover:bg-white/15">Log out</button></form>
            </div>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/85">
            <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-10">
                <div class="flex items-center gap-3">
                    <button type="button" class="rounded-2xl border border-slate-200 bg-white p-3 text-slate-700 shadow-sm lg:hidden dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200" @click="sidebarOpen = true" aria-label="Open navigation"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                    <div>@if(isset($header)){{ $header }}@else<h1 class="text-xl font-extrabold tracking-tight">Client Portal</h1><p class="text-sm text-slate-500 dark:text-slate-400">Manage your SaaS account and companies</p>@endif</div>
                </div>
                <a href="{{ route('companies.create') }}" class="hidden rounded-2xl bg-indigo-600 px-4 py-2.5 text-sm font-extrabold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500 sm:inline-flex">+ New company</a>
            </div>
        </header>

        <main class="min-h-[calc(100vh-76px)] bg-[radial-gradient(circle_at_top_right,_rgba(99,102,241,0.10),_transparent_35%),radial-gradient(circle_at_bottom_left,_rgba(14,165,233,0.08),_transparent_30%)] px-4 py-7 dark:bg-slate-950 sm:px-6 lg:px-10 lg:py-10">
            <div class="fixed right-4 top-24 z-50 w-full max-w-sm space-y-3">
                @foreach (['success' => 'emerald', 'status' => 'emerald', 'error' => 'red', 'warning' => 'amber'] as $key => $tone)
                    @if(session($key))<div x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,5000)" class="rounded-2xl border bg-white px-4 py-3 text-sm font-bold shadow-xl dark:bg-slate-900 {{ $tone === 'emerald' ? 'border-emerald-200 text-emerald-700' : ($tone === 'red' ? 'border-red-200 text-red-700' : 'border-amber-200 text-amber-700') }}">{{ session($key) }}</div>@endif
                @endforeach
            </div>
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
