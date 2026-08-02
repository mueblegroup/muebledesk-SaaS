<x-guest-layout>
    <div class="mb-6 text-center">
        <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-extrabold text-indigo-700">Secure client onboarding</span>
        <h1 class="mt-4 text-2xl font-black">Create your MuebleDesk account</h1>
        <p class="mt-2 text-sm text-slate-500">Create your verified client identity first. Company and billing setup follows after email verification.</p>
    </div>

    @if (class_exists(\Laravel\Socialite\Facades\Socialite::class))
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach(['google' => 'Google', 'microsoft' => 'Microsoft', 'apple' => 'Apple'] as $provider => $label)
                <a href="{{ route('social.redirect', $provider) }}" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-sm font-extrabold text-slate-700 hover:border-indigo-300 hover:text-indigo-600">{{ $label }}</a>
            @endforeach
        </div>

        <div class="my-6 flex items-center gap-3"><div class="h-px flex-1 bg-slate-200"></div><span class="text-xs font-bold uppercase text-slate-400">or use email</span><div class="h-px flex-1 bg-slate-200"></div></div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div><x-input-label for="name" value="Full legal name"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required autofocus/><x-input-error :messages="$errors->get('name')" class="mt-2"/></div>
        <div><x-input-label for="email" value="Work email"/><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required/><x-input-error :messages="$errors->get('email')" class="mt-2"/></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><x-input-label for="phone" value="Mobile number"/><x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone')" required/><x-input-error :messages="$errors->get('phone')" class="mt-2"/></div>
            <div><x-input-label for="job_title" value="Job title / role"/><x-text-input id="job_title" name="job_title" class="mt-1 block w-full" :value="old('job_title')" required/><x-input-error :messages="$errors->get('job_title')" class="mt-2"/></div>
        </div>
        <div><x-input-label for="address" value="Residential or correspondence address"/><textarea id="address" name="address" rows="3" class="mt-1 block w-full rounded-2xl border-slate-300" required>{{ old('address') }}</textarea><x-input-error :messages="$errors->get('address')" class="mt-2"/></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><x-input-label for="country_code" value="Country code"/><x-text-input id="country_code" name="country_code" maxlength="2" class="mt-1 block w-full uppercase" :value="old('country_code','MY')" required/></div>
            <div><x-input-label for="preferred_timezone" value="Timezone"/><x-text-input id="preferred_timezone" name="preferred_timezone" class="mt-1 block w-full" :value="old('preferred_timezone','Asia/Kuala_Lumpur')" required/></div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><x-input-label for="password" value="Password"/><x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required/><x-input-error :messages="$errors->get('password')" class="mt-2"/></div>
            <div><x-input-label for="password_confirmation" value="Confirm password"/><x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required/></div>
        </div>
        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm"><input type="checkbox" name="terms" value="1" class="mt-1 rounded" required><span>I confirm these details are accurate and agree to use this account as the authorised client-portal identity for my company.</span></label>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900">Email verification is mandatory before company creation or billing access. WhatsApp verification will be added later.</div>
        <x-primary-button class="w-full justify-center">Submit and verify email</x-primary-button>
        <a class="block text-center text-sm font-bold text-slate-600" href="{{ route('login') }}">Already registered? Log in</a>
    </form>
</x-guest-layout>
