<x-guest-layout>
    <div class="space-y-7">
        <div>
            <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-extrabold uppercase tracking-[.15em] text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">Client portal</span>
            <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Welcome back</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Sign in to manage company onboarding, subscriptions, billing and your invoicing workspace.</p>
        </div>

        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ session('error') }}</div>
        @endif
        <x-auth-session-status class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300" :status="session('status')" />

        @php
            $ssoProviders = collect(['google'=>'Google','microsoft'=>'Microsoft','apple'=>'Apple'])
                ->filter(fn ($label, $provider) => config("services.{$provider}.enabled")
                    && filled(config("services.{$provider}.client_id"))
                    && filled(config("services.{$provider}.client_secret"))
                    && filled(config("services.{$provider}.redirect")));
        @endphp

        @if (class_exists(\Laravel\Socialite\Facades\Socialite::class) && $ssoProviders->isNotEmpty())
            <div class="grid gap-3 {{ $ssoProviders->count() > 1 ? 'sm:grid-cols-'.$ssoProviders->count() : '' }}">
                @foreach($ssoProviders as $provider=>$label)
                    <a href="{{ route('social.redirect',$provider) }}" class="flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-extrabold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">Continue with {{ $label }}</a>
                @endforeach
            </div>
            <div class="flex items-center gap-3"><div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div><span class="text-xs font-bold uppercase tracking-[.18em] text-slate-400">or use email</span><div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div></div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf
            <div>
                <x-input-label for="email" value="Email address" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@company.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-2"/>
            </div>
            <div>
                <div class="flex items-center justify-between gap-3">
                    <x-input-label for="password" value="Password" />
                    <a href="{{ route('password.request') }}" class="mb-2 text-sm font-extrabold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Forgot password?</a>
                </div>
                <x-text-input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2"/>
            </div>
            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">
                <input type="checkbox" name="remember">
                <span>Keep me signed in on this device</span>
            </label>
            <x-primary-button class="w-full">Sign in securely</x-primary-button>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-center dark:border-slate-700 dark:bg-slate-950">
            <p class="text-sm text-slate-500 dark:text-slate-400">New to MuebleDesk?</p>
            <a href="{{ route('register') }}" class="mt-1 inline-flex text-sm font-extrabold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Create a client portal account</a>
        </div>
    </div>
</x-guest-layout>
