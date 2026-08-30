<x-client-portal-layout :company="$company">
    <x-slot name="title">Plan & Billing</x-slot>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600">Client portal</p>
            <h1 class="mt-1 text-2xl font-black">Plan & billing</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $company->name }}</p>
        </div>
    </x-slot>

    @php
        $subscription = $company->subscription;
        $activeSubscription = $subscription?->isActive() ? $subscription : null;
        $terminalStatuses = ['canceled', 'incomplete_expired'];
        $hasRecoverableStripeSubscription = $subscription?->stripe_subscription_id
            && ! in_array((string) $subscription->status, $terminalStatuses, true)
            && ! $activeSubscription;
        $canPurchase = ! $activeSubscription && ! $hasRecoverableStripeSubscription;
        $displayStatus = in_array($subscription?->status, ['active', 'trialing'], true)
            ? 'Active'
            : ucfirst(str_replace('_', ' ', $subscription?->status ?? ''));
        $periodEnd = $subscription?->current_period_ends_at ?? $subscription?->expires_at;
        $pendingPlan = $subscription?->pendingPlan;
        $hasStripeSchedule = filled($subscription?->stripe_subscription_schedule_id);
    @endphp

    <div class="space-y-7">
        @if($subscription)
            <section class="rounded-3xl border border-indigo-200 bg-indigo-50 p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wide text-indigo-600">
                            {{ $activeSubscription ? 'Current subscription' : ($hasRecoverableStripeSubscription ? 'Subscription needs attention' : 'Previous subscription') }}
                        </p>
                        <h2 class="mt-1 text-2xl font-black">{{ $subscription->plan?->name ?? 'Subscription' }}</h2>

                        @if($activeSubscription)
                            @if($subscription->auto_renew)
                                <p class="mt-1 text-sm text-slate-600">{{ $displayStatus }} · Next renewal {{ $periodEnd?->format('d M Y H:i') ?? '—' }}</p>
                            @else
                                <p class="mt-1 text-sm text-slate-600">{{ $displayStatus }} · Active until {{ $periodEnd?->format('d M Y H:i') ?? '—' }}</p>
                                <p class="mt-2 text-xs text-indigo-700">Auto-renewal is disabled. You keep access for the full paid period.</p>
                            @endif

                            @if($pendingPlan)
                                <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    @if($subscription->pending_plan_effective_at)
                                        <span class="font-bold">{{ $pendingPlan->name }}</span> is scheduled for {{ $subscription->pending_plan_effective_at->format('d M Y H:i') }}. Your current plan remains unchanged until then.
                                    @else
                                        Upgrade to <span class="font-bold">{{ $pendingPlan->name }}</span> is awaiting Stripe payment confirmation. Your current plan remains active until payment succeeds.
                                    @endif
                                </div>
                            @elseif($hasStripeSchedule)
                                <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    Stripe reports a future subscription schedule on this account. Cancel it before starting another immediate upgrade.
                                </div>
                            @endif
                        @elseif($hasRecoverableStripeSubscription)
                            <p class="mt-1 text-sm font-semibold text-amber-700">{{ $displayStatus }} · Resolve this existing Stripe subscription before starting or changing plans.</p>
                            @if($subscription->last_renewal_error)
                                <p class="mt-2 text-xs text-amber-700">{{ $subscription->last_renewal_error }}</p>
                            @endif
                        @else
                            <p class="mt-1 text-sm text-slate-600">{{ $displayStatus ?: 'Ended' }} · You can choose a plan below.</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @if($hasStripeSchedule && $subscription->stripe_subscription_id)
                            <form method="POST" action="{{ route('client-portal.billing.portal', $company) }}" onsubmit="return confirm('Cancel the future Stripe subscription change? Your current subscription will continue unchanged.');">
                                @csrf
                                <input type="hidden" name="billing_action" value="cancel_schedule">
                                <button class="rounded-2xl border border-amber-300 bg-white px-5 py-3 text-sm font-bold text-amber-700">Cancel scheduled change</button>
                            </form>
                        @endif

                        @if($subscription->stripe_customer_id)
                            <form method="POST" action="{{ route('client-portal.billing.portal', $company) }}">
                                @csrf
                                <button class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">Payment settings</button>
                            </form>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if($activeSubscription && !$pendingPlan && !$hasStripeSchedule)
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600">
                <span class="font-bold text-slate-900">Plan changes:</span>
                same-cycle upgrades take effect immediately and Stripe charges only the prorated difference. Downgrades — and changes to a different billing interval — start at the next renewal so you keep everything already paid for.
            </div>
        @endif

        <section class="grid gap-6 lg:grid-cols-3">
            @forelse($plans as $plan)
                @php
                    $isCurrentPlan = $activeSubscription
                        && (int) $activeSubscription->platform_subscription_plan_id === (int) $plan->id;
                    $isPendingPlan = $pendingPlan && (int) $pendingPlan->id === (int) $plan->id;
                    $direction = $activeSubscription?->plan ? $plan->tierDirectionComparedTo($activeSubscription->plan) : null;
                    $sameInterval = $activeSubscription?->plan ? $plan->sameBillingIntervalAs($activeSubscription->plan) : false;
                    $isImmediateUpgrade = $activeSubscription && $direction === 1 && $sameInterval;
                    $isScheduledChange = $activeSubscription && $direction !== null && $direction !== 0 && !$isImmediateUpgrade;
                @endphp

                <article class="rounded-3xl border {{ $isCurrentPlan ? 'border-indigo-400 ring-2 ring-indigo-100' : ($isPendingPlan ? 'border-amber-300 ring-2 ring-amber-100' : 'border-slate-200') }} bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap gap-2">
                        @if($isCurrentPlan)<span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-black text-indigo-700">Current plan</span>@endif
                        @if($isPendingPlan)<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-700">Pending change</span>@endif
                    </div>

                    <h2 class="{{ ($isCurrentPlan || $isPendingPlan) ? 'mt-4' : '' }} text-xl font-black">{{ $plan->name }}</h2>
                    <p class="mt-2 min-h-12 text-sm text-slate-500">{{ $plan->description }}</p>
                    <div class="mt-5">
                        <span class="text-3xl font-black">{{ $plan->currency }} {{ number_format($plan->price, 2) }}</span>
                        <span class="block text-sm text-slate-500">for {{ $plan->durationLabel() }}</span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->admin_limit) ? '∞' : $plan->admin_limit }}</p><p class="text-slate-500">Admins</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->employee_limit) ? '∞' : $plan->employee_limit }}</p><p class="text-slate-500">Employees</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->client_limit) ? '∞' : $plan->client_limit }}</p><p class="text-slate-500">Clients</p></div>
                    </div>

                    <ul class="mt-5 space-y-2 text-sm text-slate-600">
                        @foreach($plan->features ?? [] as $feature)
                            @unless($feature === \App\Models\PlatformSubscriptionPlan::FEATURE_CONFIGURATION_MARKER)
                                <li>✓ {{ $feature }}</li>
                            @endunless
                        @endforeach
                    </ul>

                    @if($isCurrentPlan)
                        <div class="mt-6 rounded-2xl bg-indigo-50 px-5 py-3 text-center text-sm font-bold text-indigo-700">
                            {{ $subscription->auto_renew ? 'Current plan · renews automatically' : 'Current plan · active until period end' }}
                        </div>
                    @elseif($activeSubscription && ($pendingPlan || $hasStripeSchedule))
                        <div class="mt-6 rounded-2xl bg-slate-100 px-5 py-3 text-center text-sm font-bold text-slate-500">Cancel the scheduled Stripe change first</div>
                    @elseif($isImmediateUpgrade)
                        <form method="POST" action="{{ route('client-portal.billing.checkout', [$company, $plan]) }}" class="mt-6">
                            @csrf
                            <button class="w-full rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">Upgrade now · prorated</button>
                            <p class="mt-2 text-center text-xs text-slate-500">Stripe charges only the remaining-period difference. Higher limits activate only after payment succeeds.</p>
                        </form>
                    @elseif($isScheduledChange)
                        @if($subscription->auto_renew)
                            <form method="POST" action="{{ route('client-portal.billing.checkout', [$company, $plan]) }}" class="mt-6">
                                @csrf
                                <button class="w-full rounded-2xl border border-indigo-300 bg-white px-5 py-3 text-sm font-bold text-indigo-700">{{ $direction === -1 ? 'Downgrade at next renewal' : 'Change at next renewal' }}</button>
                                <p class="mt-2 text-center text-xs text-slate-500">No immediate charge or loss of current-plan access.</p>
                            </form>
                        @else
                            <div class="mt-6 rounded-2xl bg-slate-100 px-5 py-3 text-center text-sm font-bold text-slate-500">Re-enable renewal before scheduling this plan</div>
                        @endif
                    @elseif($activeSubscription && $direction === null)
                        <div class="mt-6 rounded-2xl bg-amber-50 px-5 py-3 text-center text-sm font-bold text-amber-700">Admin must set Billing rank before this plan can be compared safely</div>
                    @elseif($hasRecoverableStripeSubscription)
                        <div class="mt-6 rounded-2xl bg-amber-50 px-5 py-3 text-center text-sm font-bold text-amber-700">Resolve the existing subscription in Payment settings first</div>
                    @elseif($canPurchase)
                        <form method="POST" action="{{ route('client-portal.billing.checkout', [$company, $plan]) }}" class="mt-6 space-y-3">
                            @csrf
                            <label class="flex items-center gap-2 text-sm font-bold">
                                <input type="checkbox" name="auto_renew" value="1" @checked($plan->auto_renew_default)>
                                Renew automatically
                            </label>
                            <button class="w-full rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">Purchase plan</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 p-10 text-center text-slate-500">No active plans are available yet.</div>
            @endforelse
        </section>
    </div>
</x-client-portal-layout>
