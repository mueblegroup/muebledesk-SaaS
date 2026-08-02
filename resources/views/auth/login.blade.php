<x-guest-layout>
    <div class="space-y-6">
        <div><span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-indigo-700">Client Portal</span><h1 class="mt-4 text-3xl font-black">Sign in to MuebleDesk</h1><p class="mt-2 text-sm text-slate-500">Access company onboarding, subscription billing and your IMS workspace.</p></div>

        @if(session('error'))<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error') }}</div>@endif
        <x-auth-session-status class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700" :status="session('status')" />

        @if (class_exists(\Laravel\Socialite\Facades\Socialite::class))
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach(['google'=>'Google','microsoft'=>'Microsoft','apple'=>'Apple'] as $provider=>$label)
                    <a href="{{ route('social.redirect',$provider) }}" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-sm font-extrabold text-slate-700 hover:border-indigo-300 hover:text-indigo-600">{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex items-center gap-3"><div class="h-px flex-1 bg-slate-200"></div><span class="text-xs font-bold uppercase text-slate-400">or email</span><div class="h-px flex-1 bg-slate-200"></div></div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf
            <div><label for="email" class="mb-2 block text-sm font-bold">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="block w-full text-base"><x-input-error :messages="$errors->get('email')" class="mt-2"/></div>
            <div><div class="mb-2 flex justify-between"><label for="password" class="text-sm font-bold">Password</label><a href="{{ route('password.request') }}" class="text-sm font-bold text-indigo-600">Forgot?</a></div><input id="password" type="password" name="password" required autocomplete="current-password" class="block w-full text-base"><x-input-error :messages="$errors->get('password')" class="mt-2"/></div>
            <label class="inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="remember" class="rounded">Remember me</label>
            <button type="submit" class="btn-primary w-full">Log in</button>
        </form>
        <a href="{{ route('register') }}" class="block text-center text-sm font-bold text-indigo-600">Create a client portal account</a>
    </div>
</x-guest-layout>
