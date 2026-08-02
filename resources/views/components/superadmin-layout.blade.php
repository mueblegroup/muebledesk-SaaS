<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Superadmin' }} · {{ config('app.name', 'MuebleDesk') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900">
<div class="min-h-screen lg:flex">
    <aside class="w-full bg-slate-950 text-white lg:sticky lg:top-0 lg:h-screen lg:w-72">
        <div class="border-b border-white/10 px-6 py-5"><div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-500 text-lg font-black">M</span><div><p class="font-extrabold">MuebleDesk</p><p class="text-xs text-slate-400">Platform Superadmin</p></div></div></div>
        <nav class="space-y-2 p-4"><a href="{{ route('superadmin.dashboard') }}" class="flex rounded-2xl px-4 py-3 text-sm font-semibold {{ request()->routeIs('superadmin.dashboard') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10' }}">Platform overview</a><a href="{{ route('superadmin.plans.index') }}" class="flex rounded-2xl px-4 py-3 text-sm font-semibold {{ request()->routeIs('superadmin.plans.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10' }}">Seat plans</a></nav>
        <div class="border-t border-white/10 p-4 lg:absolute lg:bottom-0 lg:w-72"><div class="rounded-2xl bg-white/5 p-4"><p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p><form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold">Log out</button></form></div></div>
    </aside>
    <main class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:px-10"><div class="mx-auto max-w-7xl">{{ $slot }}</div></main>
</div>
</body>
</html>
