<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription expired · {{ config('app.name', 'MuebleDesk') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <main class="flex min-h-screen items-center justify-center px-6 py-12">
        <section class="w-full max-w-2xl rounded-[2rem] border border-white/10 bg-white/5 p-8 text-center shadow-2xl backdrop-blur sm:p-12">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-amber-400/15 text-3xl">⌛</div>
            <p class="mt-6 text-xs font-black uppercase tracking-[0.25em] text-amber-300">Workspace unavailable</p>
            <h1 class="mt-3 text-3xl font-black sm:text-4xl">{{ $company->name }} subscription has expired</h1>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-slate-300">This company workspace is temporarily disabled because its subscription is no longer active. No company data has been deleted.</p>

            @if ($subscription?->ends_at)
                <div class="mx-auto mt-6 max-w-sm rounded-2xl border border-white/10 bg-black/20 px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Expired on</p>
                    <p class="mt-1 text-lg font-black">{{ $subscription->ends_at->timezone($company->timezone ?: config('app.timezone'))->format('d M Y, h:i A') }}</p>
                </div>
            @endif

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ $billingUrl }}" class="rounded-2xl bg-white px-6 py-3 text-sm font-black text-slate-950">Renew subscription</a>
                <a href="{{ $portalUrl }}" class="rounded-2xl border border-white/15 px-6 py-3 text-sm font-black text-white">Return to client portal</a>
            </div>
        </section>
    </main>
</body>
</html>
