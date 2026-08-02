<x-superadmin-layout>
    <x-slot name="title">{{ $company->name }}</x-slot>
    <x-slot name="header">
        <div><a href="{{ route('superadmin.companies.index') }}" class="text-xs font-bold text-indigo-600">← Companies</a><h1 class="mt-1 text-2xl font-extrabold text-slate-950 dark:text-white">{{ $company->name }}</h1><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Company profile, subscription and members.</p></div>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <form method="POST" action="{{ route('superadmin.companies.update', $company) }}" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf @method('PUT')
            <h2 class="text-lg font-extrabold">Company details</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label for="name" value="Company name"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name',$company->name)" required/></div>
                <div><x-input-label for="slug" value="Workspace slug"/><x-text-input id="slug" name="slug" class="mt-1 block w-full" :value="old('slug',$company->slug)" required/></div>
                <div><x-input-label for="registration_number" value="Registration number"/><x-text-input id="registration_number" name="registration_number" class="mt-1 block w-full" :value="old('registration_number',$company->registration_number)"/></div>
                <div><x-input-label for="tax_number" value="Tax number"/><x-text-input id="tax_number" name="tax_number" class="mt-1 block w-full" :value="old('tax_number',$company->tax_number)"/></div>
                <div><x-input-label for="email" value="Email"/><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email',$company->email)"/></div>
                <div><x-input-label for="phone" value="Phone"/><x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone',$company->phone)"/></div>
                <div><x-input-label for="currency" value="Currency"/><x-text-input id="currency" name="currency" maxlength="3" class="mt-1 block w-full" :value="old('currency',$company->currency)" required/></div>
                <div><x-input-label for="country_code" value="Country code"/><x-text-input id="country_code" name="country_code" maxlength="2" class="mt-1 block w-full" :value="old('country_code',$company->country_code)" required/></div>
                <div class="md:col-span-2"><x-input-label for="timezone" value="Timezone"/><x-text-input id="timezone" name="timezone" class="mt-1 block w-full" :value="old('timezone',$company->timezone)" required/></div>
            </div>
            <button class="btn-primary" type="submit">Save company</button>
        </form>

        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-extrabold">Subscription</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Plan</dt><dd class="font-bold">{{ $company->subscription?->plan?->name ?? 'No plan' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-bold capitalize">{{ $company->subscription?->status ?? 'unsubscribed' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Expires</dt><dd class="font-bold">{{ $company->subscription?->expires_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Auto-renew</dt><dd class="font-bold">{{ $company->subscription?->auto_renew ? 'Enabled' : 'Disabled' }}</dd></div>
                </dl>

                <form method="POST" action="{{ route('superadmin.companies.subscription.update', $company) }}" class="mt-5 space-y-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                    @csrf @method('PUT')
                    <div><x-input-label for="plan_id" value="Subscription plan"/><select id="plan_id" name="plan_id" class="mt-1 block w-full rounded-2xl border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($company->subscription?->platform_subscription_plan_id === $plan->id)>{{ $plan->name }} · {{ $plan->currency }} {{ number_format($plan->price,2) }} / {{ $plan->durationLabel() }}</option>@endforeach</select></div>
                    <label class="flex items-center gap-2 text-sm font-bold"><input type="hidden" name="auto_renew" value="0"><input type="checkbox" name="auto_renew" value="1" @checked($company->subscription?->auto_renew ?? true)> Auto-renew</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button name="action" value="activate" class="btn-primary" type="submit">Activate</button>
                        <button name="action" value="extend" class="btn-secondary" type="submit">Extend</button>
                        <button name="action" value="disable" class="rounded-2xl border border-amber-300 px-4 py-2 text-sm font-bold text-amber-700" type="submit">Disable</button>
                        <button name="action" value="expire" class="btn-danger" type="submit">Expire now</button>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-extrabold">Members</h2>
                <div class="mt-4 space-y-3">@forelse($company->users as $member)<div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800"><p class="text-sm font-bold">{{ $member->name }}</p><p class="text-xs text-slate-500">{{ $member->email }} · {{ $member->role?->value ?? $member->pivot->role }}</p></div>@empty<p class="text-sm text-slate-500">No members.</p>@endforelse</div>
            </section>
        </div>
    </div>
</x-superadmin-layout>
