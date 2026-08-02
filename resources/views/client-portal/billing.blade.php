<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Plan & Billing · {{ config('app.name', 'MuebleDesk') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900">
<div class="min-h-screen lg:flex">
    <aside class="w-full bg-slate-950 text-white lg:sticky lg:top-0 lg:h-screen lg:w-72"><div class="border-b border-white/10 px-6 py-5"><a href="{{ route('client-portal.dashboard') }}" class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500 text-lg font-black">M</span><div><p class="font-extrabold">MuebleDesk</p><p class="text-xs text-slate-400">SaaS Client Portal</p></div></a></div><nav class="space-y-2 p-4"><a href="{{ route('client-portal.dashboard') }}" class="flex rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10">Overview</a><a href="{{ route('client-portal.billing.index', $company) }}" class="flex rounded-2xl bg-white/10 px-4 py-3 text-sm font-bold">Plans & billing</a><a href="{{ route('profile.edit') }}" class="flex rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10">Account settings</a></nav><div class="border-t border-white/10 p-4 lg:absolute lg:bottom-0 lg:w-72"><div class="rounded-2xl bg-white/5 p-4"><p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p><form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold">Log out</button></form></div></div></aside>
    <main class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:px-10"><div class="mx-auto max-w-7xl space-y-7">
        <header><a href="{{ route('client-portal.dashboard') }}" class="text-sm font-bold text-indigo-600">← Client portal</a><h1 class="mt-3 text-3xl font-black">Plan & billing</h1><p class="mt-1 text-sm text-slate-500">{{ $company->name }}</p></header>
        @foreach(['success'=>'emerald','error'=>'red'] as $key=>$color)@if(session($key))<div class="rounded-2xl border px-5 py-4 text-sm font-semibold {{ $color==='emerald'?'border-emerald-200 bg-emerald-50 text-emerald-800':'border-red-200 bg-red-50 text-red-800' }}">{{ session($key) }}</div>@endif @endforeach
        @if($company->subscription)
            <section class="rounded-3xl border border-indigo-200 bg-indigo-50 p-6"><div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"><div><p class="text-sm font-bold uppercase tracking-wide text-indigo-600">Current subscription</p><h2 class="mt-1 text-2xl font-black">{{ $company->subscription->plan?->name ?? 'Subscription' }}</h2><p class="mt-1 text-sm text-slate-600">{{ ucfirst($company->subscription->status) }} · Expires {{ $company->subscription->expires_at?->format('d M Y H:i') ?? '—' }}</p></div><div class="flex flex-wrap gap-3"><form method="POST" action="{{ route('client-portal.billing.auto-renew',$company) }}">@csrf @method('PATCH')<input type="hidden" name="auto_renew" value="{{ $company->subscription->auto_renew ? 0 : 1 }}"><button class="rounded-2xl border border-indigo-300 bg-white px-5 py-3 text-sm font-bold text-indigo-700">Auto-renew: {{ $company->subscription->auto_renew ? 'On' : 'Off' }}</button></form>@if($company->subscription->stripe_customer_id)<form method="POST" action="{{ route('client-portal.billing.portal',$company) }}">@csrf<button class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">Payment settings</button></form>@endif</div></div></section>
        @endif
        <section class="grid gap-6 lg:grid-cols-3">
            @forelse($plans as $plan)
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black">{{ $plan->name }}</h2><p class="mt-2 min-h-12 text-sm text-slate-500">{{ $plan->description }}</p><div class="mt-5"><span class="text-3xl font-black">{{ $plan->currency }} {{ number_format($plan->price,2) }}</span><span class="block text-sm text-slate-500">for {{ $plan->durationLabel() }}</span></div>
                    <div class="mt-5 grid grid-cols-3 gap-2 text-center text-xs"><div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->admin_limit)?'∞':$plan->admin_limit }}</p><p class="text-slate-500">Admins</p></div><div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->employee_limit)?'∞':$plan->employee_limit }}</p><p class="text-slate-500">Employees</p></div><div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->client_limit)?'∞':$plan->client_limit }}</p><p class="text-slate-500">Clients</p></div></div>
                    <ul class="mt-5 space-y-2 text-sm text-slate-600">@foreach($plan->features??[] as $feature)<li>✓ {{ $feature }}</li>@endforeach</ul>
                    <form method="POST" action="{{ route('client-portal.billing.checkout',[$company,$plan]) }}" class="mt-6 space-y-3">@csrf<label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="auto_renew" value="1" @checked($plan->auto_renew_default)> Renew automatically</label><button class="w-full rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">Purchase plan</button></form>
                </article>
            @empty<div class="col-span-full rounded-3xl border border-dashed border-slate-300 p-10 text-center text-slate-500">No active plans are available yet.</div>@endforelse
        </section>
    </div></main>
</div>
</body>
</html>
