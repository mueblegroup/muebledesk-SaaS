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
            <div class="flex items-center justify-between border-b border-white/10 px-6 py-5">
                <a href="{{ route('client-portal.dashboard') }}" class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500 text-lg font-extrabold shadow-lg shadow-indigo-500/20">M</span>
                    <span>
                        <span class="block text-base font-extrabold tracking-tight">MuebleDesk</span>
                        <span class="block text-xs text-slate-400">SaaS Client Portal</span>
                    </span>
                </a>
                <button type="button" class="rounded-xl p-2 text-slate-400 hover:bg-white/10 lg:hidden" @click="mobileMenu = false">✕</button>
            </div>

            <nav class="flex-1 space-y-2 px-4 py-6">
                <a href="{{ route('client-portal.dashboard') }}" class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 text-sm font-bold text-white">
                    <span>▦</span><span>Overview</span>
                </a>
                <a href="{{ route('companies.create') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white">
                    <span>＋</span><span>Create company</span>
                </a>
                <button type="button" disabled class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-semibold text-slate-500">
                    <span>◈</span><span>Plans & billing</span><span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[10px]">Soon</span>
                </button>
                <button type="button" disabled class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-semibold text-slate-500">
                    <span>◎</span><span>Account settings</span><span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[10px]">Soon</span>
                </button>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="rounded-2xl bg-white/5 p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500 text-sm font-extrabold">
                            {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold">{{ $user?->name }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $user?->email }}</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a href="{{ route('profile.edit') }}" class="rounded-xl bg-white/10 px-3 py-2 text-center text-xs font-semibold hover:bg-white/15">Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold hover:bg-white/15">Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-xl">
                <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-10">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-xl border border-slate-200 bg-white p-2.5 lg:hidden" @click="mobileMenu = true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        <div>
                            <h1 class="text-lg font-extrabold tracking-tight text-slate-950 sm:text-xl">Client Portal</h1>
                            <p class="hidden text-sm text-slate-500 sm:block">Manage your companies and SaaS account</p>
                        </div>
                    </div>
                    <a href="{{ route('companies.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500">
                        <span class="text-lg leading-none">+</span><span class="hidden sm:inline">Create company</span><span class="sm:hidden">Company</span>
                    </a>
                </div>
            </header>

            <main class="px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
                <div class="mx-auto max-w-7xl space-y-8">
                    @if (session('success'))
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-800">
                            {{ session('error') }}
                        </div>
                    @endif

                    <section class="overflow-hidden rounded-3xl bg-slate-950 text-white shadow-2xl shadow-slate-950/10">
                        <div class="grid gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[1.4fr_0.6fr] lg:px-10 lg:py-10">
                            <div>
                                <span class="inline-flex rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-bold text-indigo-200">MuebleDesk SaaS</span>
                                <h2 class="mt-5 max-w-2xl text-3xl font-extrabold tracking-tight sm:text-4xl">Welcome back, {{ $user?->name }}.</h2>
                                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">Create and manage company workspaces, control subscriptions and open the invoicing system for each business from one central account.</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3 self-end">
                                <div class="rounded-2xl bg-white/10 p-4">
                                    <p class="text-2xl font-extrabold">{{ $companyCount }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-400">Companies</p>
                                </div>
                                <div class="rounded-2xl bg-white/10 p-4">
                                    <p class="text-2xl font-extrabold">{{ $activeCompany ? '1' : '0' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-400">Active workspace</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-5 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-xl">🏢</span>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">Complete</span>
                            </div>
                            <h3 class="mt-4 font-extrabold">Account created</h3>
                            <p class="mt-1 text-sm text-slate-500">Your central SaaS account is ready.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-xl">🌐</span>
                                <span class="rounded-full {{ $companyCount ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-bold">{{ $companyCount ? 'Complete' : 'Required' }}</span>
                            </div>
                            <h3 class="mt-4 font-extrabold">Company workspace</h3>
                            <p class="mt-1 text-sm text-slate-500">Create a company and reserve its subdomain.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-xl">💳</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">Coming soon</span>
                            </div>
                            <h3 class="mt-4 font-extrabold">Choose a plan</h3>
                            <p class="mt-1 text-sm text-slate-500">Plans will control limits, features and billing.</p>
                        </div>
                    </section>

                    <section>
                        <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                            <div>
                                <h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Your companies</h2>
                                <p class="mt-1 text-sm text-slate-500">Open a workspace or manage its SaaS account.</p>
                            </div>
                            <a href="{{ route('companies.create') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-500">+ Add another company</a>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            @foreach ($companies as $company)
                                @php
                                    $workspaceHost = $company->slug.'.'.$rootDomain;
                                    $isCurrent = $currentCompany?->is($company);
                                @endphp
                                <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-slate-950/5">
                                    <div class="border-b border-slate-100 p-6">
                                        <div class="flex items-start justify-between gap-5">
                                            <div class="flex min-w-0 items-center gap-4">
                                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-xl font-extrabold text-white shadow-lg shadow-indigo-600/20">
                                                    {{ strtoupper(substr($company->name, 0, 1)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h3 class="truncate text-xl font-extrabold text-slate-950">{{ $company->name }}</h3>
                                                        @if ($isCurrent)
                                                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">Selected</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-1 truncate text-sm text-slate-500">{{ $scheme }}://{{ $workspaceHost }}</p>
                                                </div>
                                            </div>
                                            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold capitalize text-indigo-700">{{ $company->pivot->role ?? 'member' }}</span>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 p-6 sm:grid-cols-3">
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Plan</p>
                                            <p class="mt-2 text-sm font-extrabold text-slate-900">Free trial</p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Status</p>
                                            <p class="mt-2 text-sm font-extrabold text-emerald-600">Active</p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Subdomain</p>
                                            <p class="mt-2 truncate text-sm font-extrabold text-slate-900">{{ $company->slug }}</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 p-5 sm:flex-row sm:items-center">
                                        <form method="POST" action="{{ route('companies.switch', $company) }}" class="sm:flex-1">
                                            @csrf
                                            <button type="submit" class="w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-indigo-600/15 transition hover:bg-indigo-500">
                                                Open invoicing workspace
                                            </button>
                                        </form>
                                        <button type="button" disabled class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-400">Manage company</button>
                                        <button type="button" disabled class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-400">Billing</button>
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
