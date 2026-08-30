<x-guest-layout>
    <div class="space-y-7">
        <div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-extrabold uppercase tracking-[.15em] text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">Secure onboarding</span>
                <span class="text-xs font-bold text-slate-400">Step 1 of 3</span>
            </div>
            <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Create your client portal account</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Your verified personal identity is created first. Company details and subscription setup follow after email verification.</p>
        </div>

        @php
            $ssoProviders = collect(['google'=>'Google','microsoft'=>'Microsoft','apple'=>'Apple'])
                ->filter(fn ($label, $provider) => config("services.{$provider}.enabled")
                    && filled(config("services.{$provider}.client_id"))
                    && filled(config("services.{$provider}.client_secret"))
                    && filled(config("services.{$provider}.redirect")));
            $selectedCountry = old('country_code', 'MY');
        @endphp

        @if (class_exists(\Laravel\Socialite\Facades\Socialite::class) && $ssoProviders->isNotEmpty())
            <div class="grid gap-3 {{ $ssoProviders->count() > 1 ? 'sm:grid-cols-'.$ssoProviders->count() : '' }}">
                @foreach($ssoProviders as $provider => $label)
                    <a href="{{ route('social.redirect', $provider) }}" class="flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-extrabold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">Continue with {{ $label }}</a>
                @endforeach
            </div>
            <div class="flex items-center gap-3"><div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div><span class="text-xs font-bold uppercase tracking-[.18em] text-slate-400">or register by email</span><div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div></div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-6">
            @csrf

            <section class="rounded-3xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-700 dark:bg-slate-950/60">
                <div class="mb-5"><h2 class="text-base font-black text-slate-950 dark:text-white">Identity details</h2><p class="mt-1 text-xs leading-5 text-slate-500">Use accurate details for account security, billing communication and future verification.</p></div>
                <div class="space-y-4">
                    <div><x-input-label for="name" value="Full legal name"/><x-text-input id="name" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Your full name"/><x-input-error :messages="$errors->get('name')" class="mt-2"/></div>
                    <div><x-input-label for="email" value="Work email"/><x-text-input id="email" name="email" type="email" :value="old('email')" required autocomplete="email" placeholder="you@company.com"/><x-input-error :messages="$errors->get('email')" class="mt-2"/></div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="phone" value="Mobile number"/>
                            <div class="mt-1 flex overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-4 focus-within:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950">
                                <select id="country_code" name="country_code" required aria-label="Mobile country code" class="max-w-[12rem] border-0 border-r border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-700 focus:ring-0 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    @foreach($countries as $iso => $country)
                                        <option value="{{ $iso }}" @selected($selectedCountry === $iso)>{{ $country['name'] }} ({{ $country['dial'] }})</option>
                                    @endforeach
                                </select>
                                <input id="phone" name="phone" value="{{ old('phone') }}" required autocomplete="tel-national" inputmode="tel" class="min-w-0 flex-1 border-0 bg-transparent px-4 py-3 text-sm font-medium text-slate-900 outline-none focus:ring-0 dark:text-white" placeholder="12 345 6789">
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Choose the country prefix, then enter the mobile number. We store it in international format.</p>
                            <x-input-error :messages="$errors->get('country_code')" class="mt-2"/>
                            <x-input-error :messages="$errors->get('phone')" class="mt-2"/>
                        </div>
                        <div><x-input-label for="job_title" value="Job title / role"/><x-text-input id="job_title" name="job_title" :value="old('job_title')" required placeholder="Director, Finance Manager..."/><x-input-error :messages="$errors->get('job_title')" class="mt-2"/></div>
                    </div>

                    <div><x-input-label for="address" value="Correspondence address"/><textarea id="address" name="address" rows="3" class="block min-h-28 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white" required placeholder="Address used for account correspondence">{{ old('address') }}</textarea><x-input-error :messages="$errors->get('address')" class="mt-2"/></div>

                    <div>
                        <x-input-label for="preferred_timezone" value="Timezone"/>
                        <select id="preferred_timezone" name="preferred_timezone" required autocomplete="off" class="mt-1 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach($timezones as $timezone)
                                <option value="{{ $timezone }}" @selected(old('preferred_timezone', 'Asia/Kuala_Lumpur') === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Used for invoices, reports, recurring jobs and account activity timestamps.</p>
                        <x-input-error :messages="$errors->get('preferred_timezone')" class="mt-2"/>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-700 dark:bg-slate-950/60">
                <div class="mb-5"><h2 class="text-base font-black text-slate-950 dark:text-white">Account security</h2><p class="mt-1 text-xs leading-5 text-slate-500">Use a strong, unique password. Two-factor authentication can be enabled after sign-up.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><x-input-label for="password" value="Password"/><x-text-input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Create a strong password"/><x-input-error :messages="$errors->get('password')" class="mt-2"/></div>
                    <div><x-input-label for="password_confirmation" value="Confirm password"/><x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat your password"/></div>
                </div>
            </section>

            <label class="flex items-start gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/70 p-4 text-sm leading-6 text-slate-700 dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-slate-300">
                <input type="checkbox" name="terms" value="1" class="mt-0.5" required>
                <span>I confirm that these details are accurate and that I am authorised to use this client portal account for company onboarding and billing.</span>
            </label>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300"><strong>Email verification is required.</strong> After registration we send a signed verification link to your email. Company creation, client portal access and billing remain blocked until the address is verified.</div>

            <x-primary-button class="w-full">Create account and verify email</x-primary-button>
        </form>

        <p class="text-center text-sm text-slate-500">Already registered? <a class="font-extrabold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" href="{{ route('login') }}">Sign in</a></p>
    </div>
</x-guest-layout>
