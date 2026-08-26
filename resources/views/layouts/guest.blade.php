<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-force-theme="light">
<head>
    <script>
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
    <title>{{ config('app.name', 'MuebleDesk') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-950 antialiased">
<main class="min-h-screen lg:grid lg:grid-cols-[minmax(0,.9fr)_minmax(520px,1.1fr)]">
    <section class="relative hidden overflow-hidden border-r border-slate-800 bg-slate-950 p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14">
        <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-indigo-600/20 blur-3xl"></div>
        <div class="absolute -bottom-32 right-0 h-96 w-96 rounded-full bg-indigo-400/10 blur-3xl"></div>
        <div class="relative">
            <a href="{{ route('marketing.home') }}" class="inline-flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-500 text-xl font-black text-white shadow-xl shadow-indigo-950/30">M</span>
                <span><span class="block text-lg font-black text-white">{{ config('app.name', 'MuebleDesk') }}</span><span class="block text-xs text-slate-400">Business operations platform</span></span>
            </a>
        </div>
        <div class="relative max-w-xl">
            <span class="inline-flex rounded-full border border-indigo-400/20 bg-indigo-500/10 px-3 py-1 text-xs font-extrabold uppercase tracking-[.18em] text-indigo-200">One secure workspace</span>
            <h1 class="mt-6 text-4xl font-black leading-tight tracking-tight text-white xl:text-5xl">Run quotations, invoices, payments and e-Invoices without fragmented tools.</h1>
            <p class="mt-5 text-base leading-7 text-slate-300">MuebleDesk gives each company an isolated workspace for clients, recurring billing, expenses, reporting, Malaysian MyInvois workflows and team access.</p>
            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['Verified onboarding', 'Email verification and secure account controls.'],
                    ['Company isolation', 'Separate data, users and subscriptions per workspace.'],
                    ['Complete billing flow', 'Quotation to invoice, payment and receipt.'],
                    ['Malaysia ready', 'MyInvois e-Invoice submission and tracking.'],
                ] as [$title, $text])
                    <div class="rounded-2xl border border-slate-700/80 bg-slate-900/70 p-4 backdrop-blur">
                        <p class="text-sm font-extrabold text-white">{{ $title }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-400">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        <p class="relative text-xs text-slate-500">Secure SaaS invoicing and business operations by Mueble Group.</p>
    </section>

    <section class="relative flex min-h-screen items-center justify-center bg-white px-4 py-8 sm:px-8 lg:px-10 xl:px-16">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-[radial-gradient(circle_at_top,rgba(79,70,229,.08),transparent_65%)]"></div>
        <div class="relative w-full max-w-2xl">
            <div class="mb-7 flex items-center justify-between lg:hidden">
                <a href="{{ route('marketing.home') }}" class="inline-flex items-center gap-3 text-slate-950">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-500 text-lg font-black text-white shadow-lg shadow-indigo-600/20">M</span>
                    <span class="font-black">{{ config('app.name', 'MuebleDesk') }}</span>
                </a>
                <a href="{{ route('marketing.home') }}" class="text-sm font-bold text-slate-500 transition hover:text-indigo-600">Back to website</a>
            </div>
            <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-2xl shadow-slate-950/5 sm:p-8">
                {{ $slot }}
            </div>
            <p class="mt-6 text-center text-xs leading-5 text-slate-500">By continuing, you agree to use MuebleDesk responsibly and keep your account credentials secure.</p>
        </div>
    </section>
</main>
</body>
</html>
