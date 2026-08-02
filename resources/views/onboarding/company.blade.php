<x-client-portal-layout>
    <x-slot name="title">Create Company</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white sm:text-2xl">Create company</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set up your company workspace and reserve its subdomain.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        <form method="POST" action="{{ route('companies.store') }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            @csrf

            <div>
                <h2 class="text-2xl font-extrabold text-slate-950 dark:text-white">Company details</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This company will have its own isolated IMS workspace.</p>
            </div>

            <div>
                <x-input-label for="name" value="Company name" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label for="registration_number" value="Registration number" /><x-text-input id="registration_number" name="registration_number" type="text" class="mt-1 block w-full" :value="old('registration_number')" /></div>
                <div><x-input-label for="tax_number" value="Tax number" /><x-text-input id="tax_number" name="tax_number" type="text" class="mt-1 block w-full" :value="old('tax_number')" /></div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label for="email" value="Company email" /><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', auth()->user()->email)" /></div>
                <div><x-input-label for="phone" value="Company phone" /><x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" /></div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div><x-input-label for="currency" value="Currency" /><x-text-input id="currency" name="currency" type="text" maxlength="3" class="mt-1 block w-full uppercase" :value="old('currency', 'MYR')" required /></div>
                <div><x-input-label for="country_code" value="Country code" /><x-text-input id="country_code" name="country_code" type="text" maxlength="2" class="mt-1 block w-full uppercase" :value="old('country_code', 'MY')" required /></div>
                <div><x-input-label for="timezone" value="Timezone" /><x-text-input id="timezone" name="timezone" type="text" class="mt-1 block w-full" :value="old('timezone', 'Asia/Kuala_Lumpur')" required /></div>
            </div>

            <div class="flex justify-end"><x-primary-button>Create company and continue</x-primary-button></div>
        </form>
    </div>
</x-client-portal-layout>
