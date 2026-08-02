<x-superadmin-layout>
    <x-slot name="title">Seat Plans</x-slot>

    <div class="space-y-7">
        <header><p class="text-xs font-bold uppercase tracking-[0.2em] text-violet-600">Platform billing</p><h1 class="mt-2 text-3xl font-black">Seat-based subscription plans</h1><p class="mt-1 text-sm text-slate-500">Every Stripe quantity represents one purchased company user seat.</p></header>

        @if (session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif

        <div class="grid gap-6 xl:grid-cols-[420px_1fr]">
            <form method="POST" action="{{ route('superadmin.plans.store') }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-lg font-extrabold">Create plan</h2>
                <div><x-input-label for="name" value="Plan name"/><x-text-input id="name" name="name" class="mt-1 block w-full" required/></div>
                <div><x-input-label for="description" value="Description"/><textarea id="description" name="description" class="mt-1 block w-full rounded-2xl border-slate-300"></textarea></div>
                <div class="grid grid-cols-2 gap-3"><div><x-input-label for="price_per_seat" value="Price / seat"/><x-text-input id="price_per_seat" name="price_per_seat" type="number" step="0.01" min="0" class="mt-1 block w-full" required/></div><div><x-input-label for="currency" value="Currency"/><x-text-input id="currency" name="currency" value="MYR" maxlength="3" class="mt-1 block w-full" required/></div></div>
                <div class="grid grid-cols-2 gap-3"><div><x-input-label for="billing_interval" value="Interval"/><select id="billing_interval" name="billing_interval" class="mt-1 block w-full rounded-2xl border-slate-300"><option value="month">Monthly</option><option value="year">Yearly</option></select></div><div><x-input-label for="trial_days" value="Trial days"/><x-text-input id="trial_days" name="trial_days" type="number" min="0" value="0" class="mt-1 block w-full" required/></div></div>
                <div class="grid grid-cols-2 gap-3"><div><x-input-label for="minimum_seats" value="Minimum seats"/><x-text-input id="minimum_seats" name="minimum_seats" type="number" min="1" value="1" class="mt-1 block w-full" required/></div><div><x-input-label for="maximum_seats" value="Maximum seats"/><x-text-input id="maximum_seats" name="maximum_seats" type="number" min="1" class="mt-1 block w-full"/></div></div>
                <div><x-input-label for="stripe_product_id" value="Stripe product ID"/><x-text-input id="stripe_product_id" name="stripe_product_id" class="mt-1 block w-full" placeholder="prod_..."/></div>
                <div><x-input-label for="stripe_price_id" value="Stripe recurring price ID"/><x-text-input id="stripe_price_id" name="stripe_price_id" class="mt-1 block w-full" placeholder="price_..."/></div>
                <div><x-input-label for="features_text" value="Features (one per line)"/><textarea id="features_text" name="features_text" rows="5" class="mt-1 block w-full rounded-2xl border-slate-300"></textarea></div>
                <input type="hidden" name="sort_order" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <x-primary-button>Create plan</x-primary-button>
            </form>

            <div class="space-y-4">
                @forelse ($plans as $plan)
                    <form method="POST" action="{{ route('superadmin.plans.update', $plan) }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        @csrf @method('PUT')
                        <div class="grid gap-3 md:grid-cols-2"><x-text-input name="name" :value="$plan->name" required/><x-text-input name="description" :value="$plan->description"/></div>
                        <div class="grid gap-3 md:grid-cols-4"><x-text-input name="price_per_seat" type="number" step="0.01" :value="$plan->price_per_seat" required/><x-text-input name="currency" :value="$plan->currency" required/><select name="billing_interval" class="rounded-2xl border-slate-300"><option value="month" @selected($plan->billing_interval==='month')>Monthly</option><option value="year" @selected($plan->billing_interval==='year')>Yearly</option></select><x-text-input name="trial_days" type="number" :value="$plan->trial_days" required/></div>
                        <div class="grid gap-3 md:grid-cols-4"><x-text-input name="minimum_seats" type="number" :value="$plan->minimum_seats" required/><x-text-input name="maximum_seats" type="number" :value="$plan->maximum_seats"/><x-text-input name="stripe_product_id" :value="$plan->stripe_product_id" placeholder="prod_..."/><x-text-input name="stripe_price_id" :value="$plan->stripe_price_id" placeholder="price_..."/></div>
                        <textarea name="features_text" rows="3" class="block w-full rounded-2xl border-slate-300">{{ implode("\n", $plan->features ?? []) }}</textarea>
                        <input type="hidden" name="sort_order" value="{{ $plan->sort_order }}"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Active</label>
                        <div class="flex items-center justify-between"><x-primary-button>Save plan</x-primary-button><span class="text-xs text-slate-500">{{ $plan->subscriptions()->count() }} subscription(s)</span></div>
                    </form>
                    <form method="POST" action="{{ route('superadmin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this plan?')" class="-mt-3 flex justify-end px-6">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-600">Delete plan</button></form>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 p-10 text-center text-slate-500">No plans created yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-superadmin-layout>
