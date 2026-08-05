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

        @if (class_exists(\Laravel\Socialite\Facades\Socialite::class))
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach(['google' => 'Google', 'microsoft' => 'Microsoft', 'apple' => 'Apple'] as $provider => $label)
                    <a href="{{ route('social.redirect', $provider) }}" class="flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-extrabold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ $label }}</a>
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
                        <div><x-input-label for="phone" value="Mobile number"/><x-text-input id="phone" name="phone" :value="old('phone')" required autocomplete="tel" placeholder="+60 12 345 6789"/><x-input-error :messages="$errors->get('phone')" class="mt-2"/></div>
                        <div><x-input-label for="job_title" value="Job title / role"/><x-text-input id="job_title" name="job_title" :value="old('job_title')" required placeholder="Director, Finance Manager..."/><x-input-error :messages="$errors->get('job_title')" class="mt-2"/></div>
                    </div>
                    <div><x-input-label for="address" value="Correspondence address"/><textarea id="address" name="address" rows="3" class="block min-h-28 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white" required placeholder="Address used for account correspondence">{{ old('address') }}</textarea><x-input-error :messages="$errors->get('address')" class="mt-2"/></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><x-input-label for="country_code" value="Country code"/><x-text-input id="country_code" name="country_code" maxlength="2" class="uppercase" :value="old('country_code','MY')" required/><x-input-error :messages="$errors->get('country_code')" class="mt-2"/></div>
                        <div><x-input-label for="preferred_timezone" value="Timezone"/><x-text-input id="preferred_timezone" name="preferred_timezone" :value="old('preferred_timezone','Asia/Kuala_Lumpur')" required/><x-input-error :messages="$errors->get('preferred_timezone')" class="mt-2"/></div>
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

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300"><strong>Email verification is required.</strong> You cannot create a company or access billing until the verification link is completed. WhatsApp verification can be added later.</div>

            <x-primary-button class="w-full">Create account and verify email</x-primary-button>
        </form>

        <p class="text-center text-sm text-slate-500">Already registered? <a class="font-extrabold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" href="{{ route('login') }}">Sign in</a></p>
    </div>
</x-guest-layout>
