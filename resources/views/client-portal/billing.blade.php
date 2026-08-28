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
                                <p class="mt-2 text-xs text-indigo-700">Your existing Stripe subscription will renew automatically. No additional payment is required before the renewal date.</p>
                            @else
                                <p class="mt-1 text-sm text-slate-600">{{ $displayStatus }} · Active until {{ $periodEnd?->format('d M Y H:i') ?? '—' }}</p>
                                <p class="mt-2 text-xs text-indigo-700">Auto-renewal is disabled. You keep access for the full paid period and can subscribe again after this subscription ends.</p>
                            @endif
                        @elseif($hasRecoverableStripeSubscription)
                            <p class="mt-1 text-sm font-semibold text-amber-700">{{ $displayStatus }} · This existing Stripe subscription must be resolved before a new subscription can be created.</p>
                            @if($subscription->last_renewal_error)
                                <p class="mt-2 text-xs text-amber-700">{{ $subscription->last_renewal_error }}</p>
                            @endif
                        @else
                            <p class="mt-1 text-sm text-slate-600">{{ $displayStatus ?: 'Ended' }} · You can choose a plan below.</p>
                        @endif
                    </div>

                    @if($subscription->stripe_customer_id)
                        <form method="POST" action="{{ route('client-portal.billing.portal', $company) }}">
                            @csrf
                            <button class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">Payment settings</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="grid gap-6 lg:grid-cols-3">
            @forelse($plans as $plan)
                @php
                    $isCurrentPlan = $activeSubscription
                        && (int) $activeSubscription->platform_subscription_plan_id === (int) $plan->id;
                    $blockedByActiveSubscription = (bool) $activeSubscription;
                @endphp

                <article class="rounded-3xl border {{ $isCurrentPlan ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-slate-200' }} bg-white p-6 shadow-sm">
                    @if($isCurrentPlan)
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-black text-indigo-700">Current plan</span>
                    @endif

                    <h2 class="{{ $isCurrentPlan ? 'mt-4' : '' }} text-xl font-black">{{ $plan->name }}</h2>
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
                            <li>✓ {{ $feature }}</li>
                        @endforeach
                    </ul>

                    @if($isCurrentPlan)
                        <div class="mt-6 rounded-2xl bg-indigo-50 px-5 py-3 text-center text-sm font-bold text-indigo-700">
                            {{ $subscription->auto_renew ? 'Current plan · renews automatically' : 'Current plan · active until period end' }}
                        </div>
                    @elseif($blockedByActiveSubscription)
                        <div class="mt-6 rounded-2xl bg-slate-100 px-5 py-3 text-center text-sm font-bold text-slate-500">Available after the current subscription ends</div>
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
