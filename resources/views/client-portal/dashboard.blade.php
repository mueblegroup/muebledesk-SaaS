<x-client-portal-layout :company="$currentCompany">
    <x-slot name="title">Client Portal</x-slot>
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
        $currentUsage = $currentCompany?->planUsage() ?? [];
        $currentAtLimit = collect($currentUsage)->filter(fn ($usage) => $usage['at_limit'] ?? false);
        $currentNearLimit = collect($currentUsage)->filter(fn ($usage) => $usage['near_limit'] ?? false);
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
                    @if ($currentCompany)
                        <div class="mt-6">
                            <a href="{{ route(Route::has('client-portal.billing.show') ? 'client-portal.billing.show' : 'client-portal.billing.index', $currentCompany) }}" class="rounded-2xl border border-white/20 bg-white/10 px-5 py-3 text-sm font-extrabold text-white backdrop-blur transition hover:bg-white/15">Manage plan</a>
                        </div>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2">
                    @foreach ([['Companies', $companyCount], ['Active plans', $activeSubscriptions], ['Admins', $totalAdmins], ['Employees', $totalEmployees]] as [$label, $value])
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-4 backdrop-blur"><p class="text-2xl font-black sm:text-3xl">{{ $value }}</p><p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($currentCompany && ($currentAtLimit->isNotEmpty() || $currentNearLimit->isNotEmpty()))
            @php
                $critical = $currentAtLimit->isNotEmpty();
                $affected = ($critical ? $currentAtLimit : $currentNearLimit)->pluck('label')->implode(', ');
            @endphp
            <section class="overflow-hidden rounded-[2rem] border {{ $critical ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} shadow-sm">
                <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $critical ? 'bg-red-600' : 'bg-amber-500' }} text-xl font-black text-white">{{ $critical ? '!' : '↑' }}</div>
                        <div>
                            <p class="text-xs font-black uppercase tracking-[.16em] {{ $critical ? 'text-red-700' : 'text-amber-700' }}">{{ $critical ? 'Plan capacity reached' : 'You are approaching your plan limit' }}</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $critical ? 'Your team growth is currently limited.' : 'Make room for your next hire or customer.' }}</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 {{ $critical ? 'text-red-800' : 'text-amber-800' }}">
                                {{ $critical ? 'Your current plan has reached capacity for '.$affected.'. Upgrade now to continue adding accounts without interrupting onboarding.' : 'Your '.$affected.' usage is already at 80% or more. Upgrading early keeps new-user onboarding available when you need it.' }}
                            </p>
                        </div>
                    </div>
                    <a href="{{ route(Route::has('client-portal.billing.show') ? 'client-portal.billing.show' : 'client-portal.billing.index', $currentCompany) }}" class="shrink-0 rounded-2xl {{ $critical ? 'bg-red-600 hover:bg-red-500' : 'bg-amber-500 hover:bg-amber-400' }} px-5 py-3 text-center text-sm font-black text-white shadow-sm transition">Upgrade plan</a>
                </div>
            </section>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            @foreach ([['Account ready', 'Your SaaS identity and secure login are active.', '✓'], ['Company workspace', 'Your company subdomain is ready to open.', '⌂'], ['Subscription access', $activeSubscriptions ? 'Your active plan controls admin, employee and client limits.' : 'Choose a plan to activate the company workspace.', '◇']] as [$title, $description, $icon])
                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm backdrop-blur transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-slate-950/5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-950 text-lg font-black text-white">{{ $icon }}</div>
                    <h3 class="mt-4 font-extrabold text-slate-950">{{ $title }}</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ $description }}</p>
                </div>
            @endforeach
        </section>

        <section>
            <div class="mb-5"><h2 class="text-2xl font-black tracking-tight text-slate-950">Your company</h2><p class="mt-1 text-sm text-slate-500">Open the workspace, manage its subscription, or set the local timezone used for recurring invoices.</p></div>
            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ($companies as $company)
                    @php
                        $workspaceHost = $company->slug.'.'.$rootDomain;
                        $subscription = $company->subscription;
                        $plan = $subscription?->plan;
                        $isActive = $subscription?->isActive() ?? false;
                        $usage = $company->planUsage();
                        $formatLimit = fn ($limit) => $limit === null ? 'Unlimited' : $limit;
                        $capacityIssues = collect($usage)->filter(fn ($item) => ($item['near_limit'] ?? false) || ($item['at_limit'] ?? false));
                        $hasFullLimit = $capacityIssues->contains(fn ($item) => $item['at_limit'] ?? false);
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
                                @foreach (['admin', 'employee', 'customer'] as $role)
                                    @php $item = $usage[$role]; @endphp
                                    <div class="rounded-2xl {{ $item['at_limit'] ? 'bg-red-50' : ($item['near_limit'] ? 'bg-amber-50' : 'bg-slate-50') }} p-4">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-[11px] font-bold uppercase tracking-wide {{ $item['at_limit'] ? 'text-red-500' : ($item['near_limit'] ? 'text-amber-600' : 'text-slate-400') }}">{{ $item['label'] }}</p>
                                            @if ($item['percentage'] !== null)<span class="text-[10px] font-black {{ $item['at_limit'] ? 'text-red-600' : ($item['near_limit'] ? 'text-amber-700' : 'text-slate-400') }}">{{ $item['percentage'] }}%</span>@endif
                                        </div>
                                        <p class="mt-2 text-sm font-extrabold text-slate-950">{{ $item['used'] }} / {{ $formatLimit($item['limit']) }}</p>
                                        @if ($item['limit'] !== null)
                                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/80"><div class="h-full rounded-full {{ $item['at_limit'] ? 'bg-red-500' : ($item['near_limit'] ? 'bg-amber-500' : 'bg-indigo-500') }}" style="width: {{ $item['percentage'] }}%"></div></div>
                                            @php $remaining = max(0, $item['limit'] - $item['used']); @endphp
                                            @if ($item['at_limit'])
                                                <p class="mt-2 text-[11px] font-bold text-red-700">No slots remaining</p>
                                            @elseif ($item['near_limit'])
                                                <p class="mt-2 text-[11px] font-bold text-amber-700">Only {{ $remaining }} {{ Str::singular(strtolower($item['label'])) }} slot{{ $remaining === 1 ? '' : 's' }} left</p>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if ($capacityIssues->isNotEmpty())
                                <div class="mt-4 flex flex-col gap-3 rounded-2xl border {{ $hasFullLimit ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-black {{ $hasFullLimit ? 'text-red-800' : 'text-amber-800' }}">{{ $hasFullLimit ? 'You have reached a plan limit.' : 'Your company is getting close to capacity.' }}</p>
                                        <p class="mt-1 text-xs leading-5 {{ $hasFullLimit ? 'text-red-700' : 'text-amber-700' }}">{{ $hasFullLimit ? 'Upgrade to keep adding users and customers.' : 'Consider upgrading now so growth is not interrupted when another account needs access.' }}</p>
                                    </div>
                                    <a href="{{ route(Route::has('client-portal.billing.show') ? 'client-portal.billing.show' : 'client-portal.billing.index', $company) }}" class="shrink-0 rounded-xl {{ $hasFullLimit ? 'bg-red-600 hover:bg-red-500' : 'bg-amber-500 hover:bg-amber-400' }} px-4 py-2.5 text-center text-xs font-black text-white transition">View upgrade options</a>
                                </div>
                            @endif

                            <div class="mt-3 rounded-2xl border border-slate-100 px-4 py-3 text-xs text-slate-500">Your access role: <span class="font-extrabold capitalize text-slate-700">{{ $company->pivot->role ?? 'member' }}</span>@if ($subscription?->ends_at)<span class="mx-2">·</span>Active until {{ $subscription->ends_at->format('d M Y') }}@endif</div>

                            <form method="POST" action="{{ route('companies.timezone.update', $company) }}" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                @csrf
                                @method('PUT')
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div class="min-w-0 flex-1">
                                        <label for="timezone-{{ $company->id }}" class="mb-2 block text-xs font-extrabold uppercase tracking-wide text-slate-500">Company timezone</label>
                                        <select id="timezone-{{ $company->id }}" name="timezone" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach ($timezones as $timezone)
                                                <option value="{{ $timezone }}" @selected(old('timezone', $company->timezone ?: 'UTC') === $timezone)>{{ $timezone }}</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-2 text-xs leading-5 text-slate-500">Recurring invoices are generated according to this company’s local calendar date. Daylight-saving changes are handled automatically.</p>
                                    </div>
                                    <button type="submit" class="shrink-0 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-extrabold text-white transition hover:bg-indigo-500">Save timezone</button>
                                </div>
                            </form>
                        </div>
                        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/80 p-5 sm:flex-row">
                            <form method="POST" action="{{ route('companies.switch', $company) }}" class="flex-1">@csrf<button class="w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-extrabold text-white transition group-hover:bg-indigo-600">Open workspace</button></form>
                            <a href="{{ route(Route::has('client-portal.billing.show') ? 'client-portal.billing.show' : 'client-portal.billing.index', $company) }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-extrabold text-slate-700 transition hover:border-indigo-200 hover:text-indigo-600">Plan & billing</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-client-portal-layout>
