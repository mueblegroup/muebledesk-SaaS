<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-950 sm:text-2xl">Client Portal</h1>
            <p class="mt-1 text-sm text-slate-500">Company access, subscription limits and workspace management.</p>
        </div>
    </x-slot>

    @php
        $companyCount = $companies->count();
        $activeSubscriptions = $companies->filter(fn ($company) => $company->subscription?->isActive())->count();
        $totalAdmins = $companies->sum(fn ($company) => $company->roleUsage('admin'));
        $totalEmployees = $companies->sum(fn ($company) => $company->roleUsage('employee'));
        $totalClients = $companies->sum(fn ($company) => $company->clientUsage());
    @endphp

    <div class="mx-auto max-w-7xl space-y-8">
        <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-950/15 sm:px-8 lg:px-10 lg:py-10">
            <div class="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-indigo-500/30 blur-3xl"></div>
            <div class="absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-cyan-400/20 blur-3xl"></div>
            <div class="relative grid gap-8 lg:grid-cols-[1.35fr_.65fr] lg:items-end">
                <div>
                    <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-extrabold text-indigo-100 backdrop-blur">MuebleDesk SaaS</span>
                    <h2 class="mt-5 max-w-3xl text-3xl font-black tracking-tight sm:text-4xl">Manage your company from one secure account.</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">Review your subscription limits, billing status and company workspace access.</p>
                    @if ($currentCompany && Route::has('client-portal.billing.show'))
                        <div class="mt-6">
                            <a href="{{ route('client-portal.billing.show', $currentCompany) }}" class="rounded-2xl border border-white/20 bg-white/10 px-5 py-3 text-sm font-extrabold text-white backdrop-blur transition hover:bg-white/15">Manage plan</a>
                        </div>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2">
                    @foreach ([
                        ['Companies', $companyCount],
                        ['Active plans', $activeSubscriptions],
                        ['Admins', $totalAdmins],
                        ['Employees', $totalEmployees],
                    ] as [$label, $value])
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-4 backdrop-blur"><p class="text-2xl font-black sm:text-3xl">{{ $value }}</p><p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['Account ready', 'Your SaaS identity and secure login are active.', '✓'],
                ['Company workspace', 'Your company subdomain is ready to open.', '⌂'],
                ['Subscription access', $activeSubscriptions ? 'Your active plan controls admin, employee and client limits.' : 'Choose a plan to activate the company workspace.', '◇'],
            ] as [$title, $description, $icon])
                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm backdrop-blur transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-slate-950/5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-950 text-lg font-black text-white">{{ $icon }}</div>
                    <h3 class="mt-4 font-extrabold text-slate-950">{{ $title }}</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ $description }}</p>
                </div>
            @endforeach
        </section>

        <section>
            <div class="mb-5">
                <h2 class="text-2xl font-black tracking-tight text-slate-950">Your company</h2>
                <p class="mt-1 text-sm text-slate-500">Open the workspace or manage its subscription and role allowances.</p>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ($companies as $company)
                    @php
                        $workspaceHost = $company->slug.'.'.$rootDomain;
                        $subscription = $company->subscription;
                        $plan = $subscription?->plan;
                        $isActive = $subscription?->isActive() ?? false;
                        $adminUsed = $company->roleUsage('admin');
                        $employeeUsed = $company->roleUsage('employee');
                        $clientUsed = $company->clientUsage();
                        $formatLimit = fn ($limit) => $limit === null ? 'Unlimited' : $limit;
                    @endphp
                    <article class="group overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-950/10">
                        <div class="p-6 sm:p-7">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 items-center gap-4">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-xl font-black text-white shadow-lg shadow-indigo-500/20">{{ strtoupper(substr($company->name, 0, 1)) }}</div>
                                    <div class="min-w-0"><h3 class="truncate text-xl font-black text-slate-950">{{ $company->name }}</h3><p class="mt-1 truncate text-sm text-slate-500">{{ $scheme }}://{{ $workspaceHost }}</p></div>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold {{ $isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $isActive ? ucfirst($subscription->status) : 'Plan required' }}</span>
                            </div>

                            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="rounded-2xl bg-slate-50 p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Plan</p><p class="mt-2 truncate text-sm font-extrabold">{{ $plan?->name ?? 'None' }}</p></div>
                                <div class="rounded-2xl bg-slate-50 p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Admins</p><p class="mt-2 text-sm font-extrabold">{{ $adminUsed }} / {{ $formatLimit($plan?->admin_limit) }}</p></div>
                                <div class="rounded-2xl bg-slate-50 p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Employees</p><p class="mt-2 text-sm font-extrabold">{{ $employeeUsed }} / {{ $formatLimit($plan?->employee_limit) }}</p></div>
                                <div class="rounded-2xl bg-slate-50 p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Clients</p><p class="mt-2 text-sm font-extrabold">{{ $clientUsed }} / {{ $formatLimit($plan?->client_limit) }}</p></div>
                            </div>

                            <div class="mt-3 rounded-2xl border border-slate-100 px-4 py-3 text-xs text-slate-500">
                                Your access role: <span class="font-extrabold capitalize text-slate-700">{{ $company->pivot->role ?? 'member' }}</span>
                                @if ($subscription?->ends_at)
                                    <span class="mx-2">·</span>Active until {{ $subscription->ends_at->format('d M Y') }}
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/80 p-5 sm:flex-row">
                            <form method="POST" action="{{ route('companies.switch', $company) }}" class="flex-1">@csrf<button class="w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-extrabold text-white transition group-hover:bg-indigo-600">Open workspace</button></form>
                            @if (Route::has('client-portal.billing.show'))<a href="{{ route('client-portal.billing.show', $company) }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-extrabold text-slate-700 transition hover:border-indigo-200 hover:text-indigo-600">Plan & billing</a>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
