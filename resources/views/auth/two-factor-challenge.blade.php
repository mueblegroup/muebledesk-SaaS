<x-guest-layout>
    <div x-data="{ useRecoveryCode: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <div class="mb-6 text-sm text-gray-600 dark:text-gray-400">
            <h2 class="mb-2 text-lg font-bold text-gray-900 dark:text-gray-100">{{ __('Two-factor verification') }}</h2>
            <p x-show="! useRecoveryCode">
                {{ __('Enter the 6-digit code from your authenticator app to continue.') }}
            </p>
            <p x-show="useRecoveryCode" x-cloak>
                {{ __('Enter one of your saved recovery codes. Use this only if you lost access to your authenticator app.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('two-factor.verify') }}">
            @csrf

            <div x-show="! useRecoveryCode">
                <x-input-label for="code" :value="__('Authenticator Code')" />
                <x-text-input id="code" class="block mt-1 w-full text-center text-2xl font-bold tracking-[0.4em]" type="text" name="code" inputmode="numeric" autofocus autocomplete="one-time-code" placeholder="123456" maxlength="6" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div x-show="useRecoveryCode" x-cloak>
                <x-input-label for="recovery_code" :value="__('Recovery Code')" />
                <x-text-input id="recovery_code" class="block mt-1 w-full" type="text" name="recovery_code" autocomplete="one-time-code" placeholder="ABCDE-FGHIJ" />
                <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
            </div>

            <div class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300" x-show="useRecoveryCode" x-cloak>
                {{ __('Recovery codes are single-use. After signing in, regenerate your recovery codes from Profile if needed.') }}
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300" @click="useRecoveryCode = ! useRecoveryCode; $nextTick(() => (useRecoveryCode ? document.getElementById('recovery_code') : document.getElementById('code'))?.focus())">
                    <span x-show="! useRecoveryCode">{{ __('Lost MFA? Use recovery code') }}</span>
                    <span x-show="useRecoveryCode" x-cloak>{{ __('Use authenticator code instead') }}</span>
                </button>

                <div class="flex items-center justify-end gap-3">
                    <button type="submit" form="logout-form" class="text-sm font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                        {{ __('Log out') }}
                    </button>

                    <x-primary-button>
                        {{ __('Verify') }}
                    </x-primary-button>
                </div>
            </div>
        </form>

        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
            @csrf
        </form>
    </div>
</x-guest-layout>
