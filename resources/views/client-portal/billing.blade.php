<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-950 dark:text-white">Plan & billing</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $company->name }} · {{ $seatsUsed }} seat{{ $seatsUsed === 1 ? '' : 's' }} currently used</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($company->subscription)
            <div class="rounded-3xl border border-indigo-200 bg-indigo-50 p-6 dark:border-indigo-900 dark:bg-indigo-950/30">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wide text-indigo-600">Current subscription</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-950 dark:text-white">{{ $company->subscription->plan?->name ?? 'Stripe subscription' }}</h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $company->subscription->seats }} seats · {{ ucfirst($company->subscription->status) }}</p>
                    </div>
                    @if ($company->subscription->stripe_customer_id)
                        <form method="POST" action="{{ route('client-portal.billing.portal', $company) }}">@csrf<x-primary-button>Manage in Stripe</x-primary-button></form>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-xl font-black text-slate-950 dark:text-white">{{ $plan->name }}</h2>
                    <p class="mt-2 min-h-12 text-sm text-slate-500">{{ $plan->description }}</p>
                    <div class="mt-5"><span class="text-3xl font-black">{{ $plan->currency }} {{ number_format($plan->price_per_seat, 2) }}</span><span class="text-sm text-slate-500"> / seat / {{ $plan->billing_interval }}</span></div>
                    <ul class="mt-5 space-y-2 text-sm text-slate-600 dark:text-slate-300">@foreach ($plan->features ?? [] as $feature)<li>✓ {{ $feature }}</li>@endforeach</ul>
                    <form method="POST" action="{{ route('client-portal.billing.checkout', [$company, $plan]) }}" class="mt-6 space-y-3">
                        @csrf
                        <div><x-input-label :for="'seats-'.$plan->id" value="Number of seats"/><x-text-input :id="'seats-'.$plan->id" name="seats" type="number" :min="max($plan->minimum_seats, $seatsUsed)" :max="$plan->maximum_seats" :value="max($company->subscription?->seats ?? 1, $seatsUsed, $plan->minimum_seats)" class="mt-1 block w-full" required/></div>
                        <x-primary-button class="w-full justify-center">Continue to Stripe</x-primary-button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
