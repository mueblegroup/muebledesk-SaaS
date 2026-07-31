<x-guest-layout>
    <div class="space-y-6">
        <div>
            <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">Welcome back</span>
            <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Sign in to your workspace</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Continue managing clients, quotations, invoices, and payments with a cleaner dashboard.</p>
        </div>

        <x-auth-session-status class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="block w-full text-base" placeholder="you@example.com">
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between gap-3">
                    <label for="password" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Password</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Forgot?</a>
                    @endif
                </div>
                <input id="password" type="password" name="password" required autocomplete="current-password" class="block w-full text-base" placeholder="Enter your password">
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <label for="remember_me" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-300">
                    <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900">
                    Remember me
                </label>

                <button type="button" onclick="window.setTheme && window.setTheme(window.getTheme && window.getTheme() === 'dark' ? 'light' : 'dark')" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Theme</button>
            </div>

            <button type="submit" class="btn-primary w-full">Log in</button>
        </form>
    </div>
</x-guest-layout>
