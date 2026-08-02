<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MuebleDesk') }} · Invoicing made simple</title>
    <meta name="description" content="Create your MuebleDesk account, set up your company, and start managing invoices, quotations, payments, expenses, recurring invoices and e-Invoices from one secure workspace.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 font-sans text-white antialiased">
    <div class="relative isolate overflow-hidden">
        <div class="absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(circle_at_top_left,rgba(99,102,241,.35),transparent_38%),radial-gradient(circle_at_top_right,rgba(217,70,239,.25),transparent_32%)]"></div>

        <header class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 sm:px-8 lg:px-10">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-fuchsia-500 text-lg font-black shadow-lg shadow-indigo-500/20">M</span>
                <span><span class="block text-base font-black">MuebleDesk</span><span class="block text-xs text-slate-400">SaaS invoicing platform</span></span>
            </a>
            <nav class="flex items-center gap-3">
                <a href="{{ route('login') }}" class="rounded-xl px-4 py-2 text-sm font-bold text-slate-300 transition hover:bg-white/10 hover:text-white">Log in</a>
                <a href="{{ route('register') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-black text-slate-950 transition hover:bg-indigo-50">Create account</a>
            </nav>
        </header>

        <main>
            <section class="mx-auto grid max-w-7xl gap-12 px-5 pb-24 pt-14 sm:px-8 lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:px-10 lg:pb-32 lg:pt-20">
                <div>
                    <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[.18em] text-indigo-200 backdrop-blur">Built for growing businesses</span>
                    <h1 class="mt-7 max-w-4xl text-5xl font-black leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl">Create your company. Run your invoicing. Grow from one secure account.</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">Register once, create one or more companies in the client portal, then enter each company’s isolated IMS workspace to manage clients, quotations, invoices, payments, expenses, recurring billing and e-Invoices.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="rounded-2xl bg-indigo-500 px-6 py-4 text-center text-sm font-black shadow-xl shadow-indigo-500/25 transition hover:-translate-y-0.5 hover:bg-indigo-400">Start with a free account</a>
                        <a href="#how-it-works" class="rounded-2xl border border-white/15 bg-white/5 px-6 py-4 text-center text-sm font-black text-white backdrop-blur transition hover:bg-white/10">See how it works</a>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-bold text-slate-400">
                        <span>✓ Secure company isolation</span><span>✓ Multiple companies</span><span>✓ Malaysia e-Invoice ready</span>
                    </div>
                </div>

                <div class="relative">
                    <div class="absolute -inset-8 -z-10 rounded-[3rem] bg-indigo-500/20 blur-3xl"></div>
                    <div class="rounded-[2rem] border border-white/10 bg-white/10 p-4 shadow-2xl backdrop-blur-xl sm:p-6">
                        <div class="rounded-[1.5rem] bg-slate-900 p-5 sm:p-7">
                            <div class="flex items-center justify-between"><div><p class="text-sm font-black">MuebleDesk Client Portal</p><p class="mt-1 text-xs text-slate-400">Choose a company workspace</p></div><span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-black text-emerald-300">Account ready</span></div>
                            <div class="mt-6 space-y-3">
                                @foreach ([['Mueble Solutions', 'mueble-solutions', 'Active'], ['Demo Furniture Sdn Bhd', 'demo-furniture', 'Trial']] as [$name, $slug, $status])
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                        <div class="flex items-center justify-between gap-4"><div><p class="font-black">{{ $name }}</p><p class="mt-1 text-xs text-slate-500">{{ $slug }}.{{ config('saas.root_domain', request()->getHost()) }}</p></div><span class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-bold text-slate-300">{{ $status }}</span></div>
                                        <div class="mt-4 rounded-xl bg-indigo-500 px-4 py-3 text-center text-xs font-black">Open IMS workspace</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="how-it-works" class="border-y border-white/10 bg-white/[.03]">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
                    <div class="max-w-2xl"><p class="text-xs font-black uppercase tracking-[.2em] text-indigo-300">Simple onboarding</p><h2 class="mt-3 text-3xl font-black sm:text-4xl">Three clear steps from visitor to invoicing workspace.</h2></div>
                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        @foreach ([
                            ['01', 'Create your account', 'Register as a client portal user using your name, email and password.'],
                            ['02', 'Create your company', 'Add your company details and reserve a secure workspace identity.'],
                            ['03', 'Enter the IMS', 'Open the company workspace and begin creating clients, quotations and invoices.'],
                        ] as [$number, $title, $description])
                            <article class="rounded-3xl border border-white/10 bg-white/5 p-6"><span class="text-sm font-black text-indigo-300">{{ $number }}</span><h3 class="mt-5 text-xl font-black">{{ $title }}</h3><p class="mt-3 text-sm leading-6 text-slate-400">{{ $description }}</p></article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10">
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['Invoices & quotations', 'Create professional documents, track status and export PDFs.'],
                        ['Payments & receipts', 'Record transactions, issue receipts and reconcile outstanding balances.'],
                        ['Recurring invoices', 'Automate repeated billing while keeping each company isolated.'],
                        ['Expenses & reports', 'Track costs and understand profitability from the same workspace.'],
                        ['Malaysia e-Invoice', 'Prepare and manage MyInvois-ready e-Invoice workflows.'],
                        ['Team access', 'Add company users and control who can access each workspace.'],
                    ] as [$title, $description])
                        <article class="rounded-3xl border border-white/10 bg-slate-900 p-6"><div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500/15 font-black text-indigo-300">✓</div><h3 class="mt-5 text-lg font-black">{{ $title }}</h3><p class="mt-2 text-sm leading-6 text-slate-400">{{ $description }}</p></article>
                    @endforeach
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-5 pb-24 sm:px-8 lg:px-10">
                <div class="overflow-hidden rounded-[2rem] bg-gradient-to-r from-indigo-500 to-fuchsia-500 px-6 py-10 text-center shadow-2xl shadow-indigo-500/20 sm:px-10 sm:py-14"><h2 class="text-3xl font-black sm:text-4xl">Ready to create your first company?</h2><p class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-indigo-50 sm:text-base">Create your client portal account, set up your company and enter the MuebleDesk IMS from one secure login.</p><a href="{{ route('register') }}" class="mt-7 inline-flex rounded-2xl bg-white px-6 py-4 text-sm font-black text-slate-950 transition hover:-translate-y-0.5">Create your account</a></div>
            </section>
        </main>

        <footer class="border-t border-white/10"><div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10"><p>© {{ now()->year }} MuebleDesk. All rights reserved.</p><div class="flex gap-5"><a href="{{ route('login') }}" class="hover:text-white">Log in</a><a href="{{ route('register') }}" class="hover:text-white">Register</a></div></div></footer>
    </div>
</body>
</html>
