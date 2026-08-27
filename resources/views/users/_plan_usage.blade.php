@php
    $usageItems = collect($planUsage ?? []);
    $atLimitItems = $usageItems->filter(fn ($item) => $item['at_limit'] ?? false);
    $nearLimitItems = $usageItems->filter(fn ($item) => $item['near_limit'] ?? false);
@endphp

<div class="space-y-4">
    @if ($atLimitItems->isNotEmpty())
        <div class="rounded-3xl border border-red-200 bg-red-50 p-5 text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-100">
            <div class="flex items-start gap-3">
                <span class="text-xl">🔴</span>
                <div>
                    <p class="font-extrabold">Plan user limit reached</p>
                    <p class="mt-1 text-sm leading-6">
                        {{ $atLimitItems->pluck('label')->join(', ') }} {{ $atLimitItems->count() === 1 ? 'has' : 'have' }} reached the limit for the {{ $planName ?? 'current' }} plan. Remove an existing account or upgrade the plan before adding another account in that role.
                    </p>
                </div>
            </div>
        </div>
    @elseif ($nearLimitItems->isNotEmpty())
        <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
            <div class="flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <p class="font-extrabold">Approaching your plan limit</p>
                    <p class="mt-1 text-sm leading-6">
                        {{ $nearLimitItems->pluck('label')->join(', ') }} {{ $nearLimitItems->count() === 1 ? 'is' : 'are' }} at least 80% used on the {{ $planName ?? 'current' }} plan. Consider upgrading before you need additional accounts.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Team seats</p>
                    <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">
                        {{ $seatsUsed ?? 0 }} / {{ $seatLimit === null ? 'Unlimited' : $seatLimit }}
                    </p>
                </div>
                @if ($seatUsagePercentage !== null)
                    <span class="rounded-full px-3 py-1 text-xs font-extrabold {{ $seatUsagePercentage >= 100 ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : ($seatUsagePercentage >= 80 ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300') }}">{{ $seatUsagePercentage }}%</span>
                @endif
            </div>
            @if ($seatUsagePercentage !== null)
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-indigo-600" style="width: {{ min(100, $seatUsagePercentage) }}%"></div>
                </div>
            @endif
            <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Combined administrator and employee capacity.</p>
        </div>

        @foreach ($usageItems as $item)
            <div class="rounded-3xl border p-5 shadow-sm {{ $item['at_limit'] ? 'border-red-200 bg-red-50 dark:border-red-900/60 dark:bg-red-950/20' : ($item['near_limit'] ? 'border-amber-200 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/20' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900') }}">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $item['label'] }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">
                            {{ $item['used'] }} / {{ $item['limit'] === null ? 'Unlimited' : $item['limit'] }}
                        </p>
                    </div>
                    @if ($item['percentage'] !== null)
                        <span class="rounded-full px-3 py-1 text-xs font-extrabold {{ $item['at_limit'] ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : ($item['near_limit'] ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300') }}">{{ $item['percentage'] }}%</span>
                    @endif
                </div>
                @if ($item['percentage'] !== null)
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full {{ $item['at_limit'] ? 'bg-red-600' : ($item['near_limit'] ? 'bg-amber-500' : 'bg-indigo-600') }}" style="width: {{ min(100, $item['percentage']) }}%"></div>
                    </div>
                @endif
                <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">
                    @if ($item['at_limit'])
                        Limit reached. New {{ strtolower($item['label']) }} are blocked by the backend.
                    @elseif ($item['near_limit'])
                        At least 80% used. Upgrade planning is recommended.
                    @else
                        {{ $item['limit'] === null ? 'No configured limit for this role.' : max(0, $item['limit'] - $item['used']).' remaining.' }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>
</div>
