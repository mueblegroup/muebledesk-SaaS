<x-guest-layout>
    <div class="mb-6 text-center">
        <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-extrabold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">Step 1 of 3</span>
        <h1 class="mt-4 text-2xl font-black text-slate-950 dark:text-white">Create your MuebleDesk account</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">This is your client portal login. After registration, you will create your company and then enter its IMS workspace.</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div>
            <x-input-label for="name" value="Full name" />
            <x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" value="Work email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs leading-5 text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
            Next: verify your email, create your company, and open the company IMS workspace.
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a class="text-center text-sm font-bold text-slate-600 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-300" href="{{ route('login') }}">Already have an account? Log in</a>
            <x-primary-button class="justify-center sm:min-w-40">Create account</x-primary-button>
        </div>
    </form>
</x-guest-layout>
