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
    <title>{{ config('app.name', 'MuebleDesk') }} · Superadmin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div x-data="{ sidebarOpen: false, theme: window.muebleTheme.get() }" @mueble-theme-changed.window="theme = $event.detail" class="min-h-screen lg:flex">
    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" @click="sidebarOpen=false"></div>
    <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-slate-950 text-white transition lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="border-b border-white/10 px-5 py-5">
            <a href="{{ route('superadmin.dashboard') }}" class="flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-500 text-xl font-black text-white shadow-lg shadow-indigo-500/25">M</span>
                <span><span class="block text-base font-extrabold">MuebleDesk</span><span class="block text-xs text-slate-400">Platform administration</span></span>
            </a>
        </div>
        <nav class="flex-1 space-y-1 px-4 py-5">
            @foreach ([
                ['Dashboard', 'superadmin.dashboard', 'superadmin.dashboard', '▦'],
                ['Plans', 'superadmin.plans.index', 'superadmin.plans.*', '◇'],
                ['Users', 'superadmin.users.index', 'superadmin.users.*', '◎'],
                ['System Guide', 'system-guide.index', 'system-guide.*', '▤'],
                ['Profile & security', 'profile.edit', 'profile.*', '⚙'],
            ] as [$label, $route, $active, $icon])
                <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs($active) ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"><span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10">{{ $icon }}</span>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 p-4">
            <div class="mb-3 grid grid-cols-3 gap-2 rounded-2xl bg-white/5 p-2 text-xs font-bold">
                <button type="button" @click="window.muebleTheme.set('light')" :class="theme==='light' ? 'bg-white text-slate-950' : 'text-slate-400'" class="rounded-xl px-2 py-2">Light</button>
                <button type="button" @click="window.muebleTheme.set('dark')" :class="theme==='dark' ? 'bg-white text-slate-950' : 'text-slate-400'" class="rounded-xl px-2 py-2">Dark</button>
                <button type="button" @click="window.muebleTheme.set('system')" :class="theme==='system' ? 'bg-white text-slate-950' : 'text-slate-400'" class="rounded-xl px-2 py-2">System</button>
            </div>
            <div class="rounded-2xl bg-white/5 p-4">
                <p class="truncate text-sm font-extrabold">{{ auth()->user()?->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ auth()->user()?->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-bold">Log out</button></form>
            </div>
        </div>
    </aside>
    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/85 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/85">
            <div class="flex items-center gap-3 px-4 py-4 sm:px-6 lg:px-10">
                <button class="rounded-2xl border border-slate-200 p-3 lg:hidden dark:border-slate-800" @click="sidebarOpen=true">☰</button>
                <div>@if(isset($header)){{ $header }}@else<h1 class="text-xl font-extrabold">Superadmin</h1>@endif</div>
            </div>
        </header>
        <main class="min-h-[calc(100vh-76px)] px-4 py-7 sm:px-6 lg:px-10 lg:py-10">
            @foreach (['success', 'error', 'warning'] as $key)
                @if(session($key))<div class="mb-5 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold shadow-sm dark:border-slate-800 dark:bg-slate-900">{{ session($key) }}</div>@endif
            @endforeach
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>