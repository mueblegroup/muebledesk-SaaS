<x-superadmin-layout>
    <x-slot name="title">Subscription Plans</x-slot>
    <x-slot name="header"><div><h1 class="text-2xl font-extrabold text-slate-950 dark:text-white">Subscription plans</h1><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Fixed pricing, flexible duration, role limits, server-enforced feature entitlements, and safe upgrade ordering.</p></div></x-slot>

    <div class="grid gap-6 xl:grid-cols-[430px_minmax(0,1fr)]">
        <form method="POST" action="{{ route('superadmin.plans.store') }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            <h2 class="text-lg font-extrabold">Create plan</h2>
            <div><x-input-label for="name" value="Plan name"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required/></div>
            <div><x-input-label for="description" value="Description"/><textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-2xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">{{ old('description') }}</textarea></div>
            <div class="grid grid-cols-2 gap-3"><div><x-input-label for="price" value="Fixed price"/><x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price')" required/></div><div><x-input-label for="currency" value="Currency"/><x-text-input id="currency" name="currency" value="{{ old('currency','MYR') }}" maxlength="3" class="mt-1 block w-full" required/></div></div>
            <div class="grid grid-cols-2 gap-3"><div><x-input-label for="duration_value" value="Duration"/><x-text-input id="duration_value" name="duration_value" type="number" min="1" value="{{ old('duration_value',1) }}" class="mt-1 block w-full" required/></div><div><x-input-label for="duration_unit" value="Unit"/><select id="duration_unit" name="duration_unit" class="mt-1 block w-full rounded-2xl border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="days">Days</option><option value="months" selected>Months</option><option value="years">Years</option></select></div></div>
            <div><x-input-label for="billing_rank" value="Billing rank"/><x-text-input id="billing_rank" name="billing_rank" type="number" min="0" value="{{ old('billing_rank',0) }}" class="mt-1 block w-full"/><p class="mt-1 text-xs text-slate-500">Higher rank = higher tier. Example: Basic 10, Pro 20, Business 30. Set this explicitly when plans have different billing intervals.</p></div>
            <div class="grid grid-cols-3 gap-3"><div><x-input-label for="admin_limit" value="Admins"/><x-text-input id="admin_limit" name="admin_limit" type="number" min="0" class="mt-1 block w-full" placeholder="Unlimited"/></div><div><x-input-label for="employee_limit" value="Employees"/><x-text-input id="employee_limit" name="employee_limit" type="number" min="0" class="mt-1 block w-full" placeholder="Unlimited"/></div><div><x-input-label for="client_limit" value="Clients"/><x-text-input id="client_limit" name="client_limit" type="number" min="0" class="mt-1 block w-full" placeholder="Unlimited"/></div></div>
            <p class="text-xs text-slate-500">Leave a role limit empty for unlimited accounts.</p>

            <div>
                <x-input-label value="Feature entitlements"/>
                <p class="mt-1 text-xs text-slate-500">These permissions are enforced by the server. Unchecked features are not available to companies on this plan.</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach($featureOptions as $key => $label)
                        <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-3 py-2 text-sm font-semibold dark:border-slate-800"><input type="checkbox" name="feature_keys[]" value="{{ $key }}" @checked(in_array($key, old('feature_keys', array_keys($featureOptions)), true))> {{ $label }}</label>
                    @endforeach
                </div>
            </div>
            <div><x-input-label for="features_text" value="Additional display features (one per line)"/><textarea id="features_text" name="features_text" rows="3" class="mt-1 block w-full rounded-2xl border-slate-300 dark:border-slate-700 dark:bg-slate-950"></textarea></div>
            <input type="hidden" name="sort_order" value="0">
            <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="auto_renew_default" value="1" checked> Auto-renew enabled by default</label>
            <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_active" value="1" checked> Plan available for purchase</label>
            <button class="btn-primary w-full" type="submit">Create plan</button>
        </form>

        <div class="space-y-4">
            @forelse ($plans as $plan)
                @php($planFeatures = collect($plan->features ?? []))
                @php($knownFeatures = $planFeatures->intersect(array_keys($featureOptions))->all())
                @php($entitlementsConfigured = $planFeatures->contains(\App\Models\PlatformSubscriptionPlan::FEATURE_CONFIGURATION_MARKER))
                @php($legacyPlan = !$entitlementsConfigured && empty($knownFeatures))
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <form method="POST" action="{{ route('superadmin.plans.update', $plan) }}" class="space-y-4">@csrf @method('PUT')
                        <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-black">{{ $plan->name }}</h2><p class="text-sm text-slate-500">{{ $plan->subscriptions()->count() }} subscription(s) · Billing rank {{ $plan->billing_rank ?: 'legacy' }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $plan->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $plan->is_active ? 'Active' : 'Hidden' }}</span></div>
                        @if($legacyPlan)<div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Legacy plan: all structured features remain allowed until you save explicit entitlements below.</div>@endif
                        <div class="grid gap-3 md:grid-cols-2"><x-text-input name="name" :value="$plan->name" required/><x-text-input name="description" :value="$plan->description"/></div>
                        <div class="grid gap-3 md:grid-cols-5"><x-text-input name="price" type="number" step="0.01" :value="$plan->price" required/><x-text-input name="currency" :value="$plan->currency" required/><x-text-input name="duration_value" type="number" min="1" :value="$plan->duration_value" required/><select name="duration_unit" class="rounded-2xl border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="days" @selected($plan->duration_unit==='days')>Days</option><option value="months" @selected($plan->duration_unit==='months')>Months</option><option value="years" @selected($plan->duration_unit==='years')>Years</option></select><x-text-input name="billing_rank" type="number" min="0" :value="$plan->billing_rank" placeholder="Billing rank"/></div>
                        <div class="grid gap-3 md:grid-cols-3"><x-text-input name="admin_limit" type="number" min="0" :value="$plan->admin_limit" placeholder="Unlimited admins"/><x-text-input name="employee_limit" type="number" min="0" :value="$plan->employee_limit" placeholder="Unlimited employees"/><x-text-input name="client_limit" type="number" min="0" :value="$plan->client_limit" placeholder="Unlimited clients"/></div>

                        <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-4">
                            @foreach($featureOptions as $key => $label)
                                <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-3 py-2 text-sm font-semibold dark:border-slate-800"><input type="checkbox" name="feature_keys[]" value="{{ $key }}" @checked($legacyPlan || in_array($key, $knownFeatures, true))> {{ $label }}</label>
                            @endforeach
                        </div>
                        <textarea name="features_text" rows="2" class="block w-full rounded-2xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">{{ $planFeatures->reject(fn($feature) => array_key_exists($feature, $featureOptions) || $feature === \App\Models\PlatformSubscriptionPlan::FEATURE_CONFIGURATION_MARKER)->implode("\n") }}</textarea>
                        <input type="hidden" name="sort_order" value="{{ $plan->sort_order }}">
                        <div class="flex flex-wrap gap-5"><label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="auto_renew_default" value="1" @checked($plan->auto_renew_default)> Auto-renew default</label><label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Available</label></div>
                        <button class="btn-primary" type="submit">Save plan</button>
                    </form>
                    @if(!$plan->subscriptions()->exists())<form method="POST" action="{{ route('superadmin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this plan?')" class="mt-3 text-right">@csrf @method('DELETE')<button class="text-sm font-bold text-red-600">Delete plan</button></form>@endif
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 p-10 text-center text-slate-500">No plans created yet.</div>
            @endforelse
        </div>
    </div>
</x-superadmin-layout>