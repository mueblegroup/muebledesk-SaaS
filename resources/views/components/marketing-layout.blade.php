<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? 'MuebleDesk is a complete SaaS invoicing, payment, expense, recurring billing and Malaysian e-Invoice platform for growing companies.' }}">
    <title>{{ $title ?? 'MuebleDesk' }} · Smart invoicing for modern businesses</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white font-sans text-slate-900 antialiased">
<div x-data="{ mobileOpen:false }" class="min-h-screen overflow-x-hidden">
    <header class="sticky top-0 z-50 border-b border-white/60 bg-white/85 backdrop-blur-xl">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ route('marketing.home') }}" class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-500 text-lg font-black text-white shadow-lg shadow-indigo-500/25">M</span>
                <span><span class="block text-base font-black tracking-tight">MuebleDesk</span><span class="block text-[11px] font-bold uppercase tracking-[.18em] text-slate-400">Business command center</span></span>
            </a>
            <nav class="hidden items-center gap-7 lg:flex">
                @foreach([
                    ['route'=>'marketing.features','label'=>'Features'],
                    ['route'=>'marketing.how-it-works','label'=>'How it works'],
                    ['route'=>'marketing.security','label'=>'Security'],
                    ['route'=>'marketing.pricing','label'=>'Pricing'],
                    ['route'=>'marketing.contact','label'=>'Contact'],
                ] as $item)
                    <a href="{{ route($item['route']) }}" class="text-sm font-bold transition {{ request()->routeIs($item['route']) ? 'text-indigo-600' : 'text-slate-600 hover:text-slate-950' }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="hidden items-center gap-3 lg:flex">
                <a href="{{ route('login') }}" class="rounded-2xl px-4 py-2.5 text-sm font-extrabold text-slate-700 hover:bg-slate-100">Log in</a>
                <a href="{{ route('register') }}" class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-slate-950/15 transition hover:-translate-y-0.5 hover:bg-indigo-600">Start your company</a>
            </div>
            <button @click="mobileOpen=!mobileOpen" class="rounded-xl border border-slate-200 p-2.5 lg:hidden">☰</button>
        </div>
        <div x-show="mobileOpen" x-cloak class="border-t border-slate-100 bg-white p-4 lg:hidden">
            <div class="grid gap-2">
                @foreach([
                    ['route'=>'marketing.features','label'=>'Features'],['route'=>'marketing.how-it-works','label'=>'How it works'],['route'=>'marketing.security','label'=>'Security'],['route'=>'marketing.pricing','label'=>'Pricing'],['route'=>'marketing.contact','label'=>'Contact'],['route'=>'login','label'=>'Log in'],['route'=>'register','label'=>'Create account'],
                ] as $item)<a href="{{ route($item['route']) }}" class="rounded-xl px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">{{ $item['label'] }}</a>@endforeach
            </div>
        </div>
    </header>

    {{ $slot }}

    <footer class="border-t border-slate-200 bg-slate-950 text-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
            <div class="lg:col-span-2"><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 font-black">M</span><span class="text-lg font-black">MuebleDesk</span></div><p class="mt-4 max-w-xl text-sm leading-7 text-slate-400">One secure SaaS platform for company onboarding, client management, quotations, invoicing, recurring billing, expenses, online payments, customer access and Malaysian MyInvois e-Invoices.</p></div>
            <div><p class="font-extrabold">Product</p><div class="mt-4 grid gap-3 text-sm text-slate-400"><a href="{{ route('marketing.features') }}">Features</a><a href="{{ route('marketing.how-it-works') }}">How it works</a><a href="{{ route('marketing.security') }}">Security</a><a href="{{ route('marketing.pricing') }}">Pricing</a></div></div>
            <div><p class="font-extrabold">Get started</p><div class="mt-4 grid gap-3 text-sm text-slate-400"><a href="{{ route('register') }}">Create account</a><a href="{{ route('login') }}">Client portal</a><a href="{{ route('marketing.contact') }}">Contact</a></div></div>
        </div>
        <div class="border-t border-white/10 px-4 py-6 text-center text-xs text-slate-500">© {{ now()->year }} MuebleDesk. Built for secure, modern business operations.</div>
    </footer>
</div>
</body>
</html>