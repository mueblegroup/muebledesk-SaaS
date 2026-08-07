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
        $activeSubscription = $company->subscription?->isActive() ? $company->subscription : null;
        $displayStatus = in_array($company->subscription?->status, ['active', 'trialing'], true)
            ? 'Active'
            : ucfirst($company->subscription?->status ?? '');
    @endphp

    <div class="space-y-7">
        @if($company->subscription)
            <section class="rounded-3xl border border-indigo-200 bg-indigo-50 p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wide text-indigo-600">Current subscription</p>
                        <h2 class="mt-1 text-2xl font-black">{{ $company->subscription->plan?->name ?? 'Subscription' }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $displayStatus }} · Renews / expires {{ $company->subscription->expires_at?->format('d M Y H:i') ?? '—' }}</p>
                        @if($activeSubscription)
                            <p class="mt-2 text-xs text-indigo-700">Buying the same plan again prepays another {{ $company->subscription->plan?->durationLabel() }} and moves your next renewal date forward.</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('client-portal.billing.auto-renew',$company) }}">@csrf @method('PATCH')<input type="hidden" name="auto_renew" value="{{ $company->subscription->auto_renew ? 0 : 1 }}"><button class="rounded-2xl border border-indigo-300 bg-white px-5 py-3 text-sm font-bold text-indigo-700">Auto-renew: {{ $company->subscription->auto_renew ? 'On' : 'Off' }}</button></form>
                        @if($company->subscription->stripe_customer_id)<form method="POST" action="{{ route('client-portal.billing.portal',$company) }}">@csrf<button class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">Payment settings</button></form>@endif
                    </div>
                </div>
            </section>
        @endif

        <section class="grid gap-6 lg:grid-cols-3">
            @forelse($plans as $plan)
                @php
                    $isCurrentPlan = $activeSubscription && (int) $activeSubscription->platform_subscription_plan_id === (int) $plan->id;
                    $isOtherWhileActive = $activeSubscription && ! $isCurrentPlan;
                @endphp
                <article class="rounded-3xl border {{ $isCurrentPlan ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-slate-200' }} bg-white p-6 shadow-sm">
                    @if($isCurrentPlan)<span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-black text-indigo-700">Current plan</span>@endif
                    <h2 class="{{ $isCurrentPlan ? 'mt-4' : '' }} text-xl font-black">{{ $plan->name }}</h2>
                    <p class="mt-2 min-h-12 text-sm text-slate-500">{{ $plan->description }}</p>
                    <div class="mt-5"><span class="text-3xl font-black">{{ $plan->currency }} {{ number_format($plan->price,2) }}</span><span class="block text-sm text-slate-500">for {{ $plan->durationLabel() }}</span></div>
                    <div class="mt-5 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->admin_limit)?'∞':$plan->admin_limit }}</p><p class="text-slate-500">Admins</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->employee_limit)?'∞':$plan->employee_limit }}</p><p class="text-slate-500">Employees</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="font-black">{{ is_null($plan->client_limit)?'∞':$plan->client_limit }}</p><p class="text-slate-500">Clients</p></div>
                    </div>
                    <ul class="mt-5 space-y-2 text-sm text-slate-600">@foreach($plan->features??[] as $feature)<li>✓ {{ $feature }}</li>@endforeach</ul>

                    @if($isOtherWhileActive)
                        <div class="mt-6 rounded-2xl bg-slate-100 px-5 py-3 text-center text-sm font-bold text-slate-500">Plan change unavailable while current plan is active</div>
                    @else
                        <form method="POST" action="{{ route('client-portal.billing.checkout',[$company,$plan]) }}" class="mt-6 space-y-3">
                            @csrf
                            @unless($isCurrentPlan)
                                <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="auto_renew" value="1" @checked($plan->auto_renew_default)> Renew automatically</label>
                            @endunless
                            <button class="w-full rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">{{ $isCurrentPlan ? 'Extend current plan' : 'Purchase plan' }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 p-10 text-center text-slate-500">No active plans are available yet.</div>
            @endforelse
        </section>
    </div>
</x-client-portal-layout>
