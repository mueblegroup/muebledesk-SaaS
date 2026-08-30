<x-client-portal-layout>
    <x-slot name="title">Create Company</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white sm:text-2xl">Create company</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set up your company workspace and reserve its subdomain.</p>
        </div>
    </x-slot>

    @php
        $selectedCountry = old('country_code', auth()->user()->country_code ?: 'MY');
        $selectedTimezone = old('timezone', auth()->user()->preferred_timezone ?: 'Asia/Kuala_Lumpur');
    @endphp

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
                <div>
                    <x-input-label for="registration_number" value="Business Registration Number (BRN)" />
                    <x-text-input id="registration_number" name="registration_number" type="text" class="mt-1 block w-full" :value="old('registration_number')" placeholder="e.g. 202301012345 / 1234567-X" autocomplete="off" />
                    <p class="mt-1 text-xs text-slate-500">Enter it exactly as issued by the business registry, including letters or hyphens if applicable.</p>
                    <x-input-error :messages="$errors->get('registration_number')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="tax_number" value="Tax Identification Number (TIN / Tax No.)" />
                    <x-text-input id="tax_number" name="tax_number" type="text" class="mt-1 block w-full" :value="old('tax_number')" placeholder="e.g. C1234567890" autocomplete="off" />
                    <p class="mt-1 text-xs text-slate-500">Use the tax identifier in the exact format issued by your tax authority.</p>
                    <x-input-error :messages="$errors->get('tax_number')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="email" value="Company email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', auth()->user()->email)" autocomplete="email" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" value="Company phone" />
                <div class="mt-1 grid grid-cols-[8.5rem_minmax(0,1fr)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-4 focus-within:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950">
                    <select id="country_code" name="country_code" required aria-label="Company phone country code" class="w-full border-0 border-r border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-700 outline-none focus:ring-0 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        @foreach($countries as $iso => $country)
                            <option value="{{ $iso }}" @selected($selectedCountry === $iso)>{{ $country['dial'] }} · {{ $iso }}</option>
                        @endforeach
                    </select>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel-national" inputmode="tel" class="min-w-0 w-full border-0 bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none placeholder:text-slate-400 focus:ring-0 dark:bg-slate-950 dark:text-white" placeholder="12 345 6789">
                </div>
                <p class="mt-1 text-xs text-slate-500">Choose the dialing code, then enter the local number. It will be stored in international format.</p>
                <x-input-error :messages="$errors->get('country_code')" class="mt-2" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-input-label for="currency" value="Currency" />
                    <x-text-input id="currency" name="currency" type="text" maxlength="3" class="mt-1 block w-full uppercase" :value="old('currency', 'MYR')" required placeholder="MYR" />
                    <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="timezone" value="Timezone" />
                    <select id="timezone" name="timezone" required class="mt-1 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @foreach($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected($selectedTimezone === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Used for invoice dates, reports, recurring jobs and company activity timestamps.</p>
                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end"><x-primary-button>Create company and continue</x-primary-button></div>
        </form>
    </div>
</x-client-portal-layout>
