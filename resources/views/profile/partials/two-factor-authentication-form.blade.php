<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Two-Factor Authentication') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Add an extra login step using Google Authenticator, Microsoft Authenticator, Authy, or any TOTP app.') }}
        </p>
    </header>

    @if (session('status') === 'two-factor-enabled')
        <div class="mt-4 rounded-md bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-950 dark:text-green-300">
            Two-factor authentication has been enabled. Save your recovery codes below.
        </div>
    @elseif (session('status') === 'two-factor-disabled')
        <div class="mt-4 rounded-md bg-yellow-50 p-4 text-sm font-medium text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300">
            Two-factor authentication has been disabled.
        </div>
    @elseif (session('status') === 'two-factor-recovery-regenerated')
        <div class="mt-4 rounded-md bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-950 dark:text-green-300">
            New recovery codes have been generated. Save them now.
        </div>
    @elseif (session('status') === 'two-factor-setup-started')
        <div class="mt-4 rounded-md bg-blue-50 p-4 text-sm font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
            Scan the QR code below, then enter the 6-digit code from your authenticator app to confirm.
        </div>
    @endif

    @if ($user->hasTwoFactorEnabled())
        <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/40">
            <p class="font-bold text-green-800 dark:text-green-200">2FA is enabled</p>
            <p class="mt-1 text-sm text-green-700 dark:text-green-300">Your account requires an authenticator code during login.</p>
        </div>

        @if (! empty($recoveryCodes))
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                <p class="font-bold text-slate-900 dark:text-white">Recovery codes</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Store these somewhere safe. Each code can be used once if you lose access to your authenticator app.</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($recoveryCodes as $code)
                        <code class="rounded-lg bg-white px-3 py-2 text-sm font-bold tracking-wider text-slate-900 dark:bg-slate-900 dark:text-white">{{ $code }}</code>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="space-y-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                @csrf
                <div>
                    <x-input-label for="two_factor_recovery_password" :value="__('Current Password')" />
                    <x-text-input id="two_factor_recovery_password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <x-secondary-button>{{ __('Regenerate Recovery Codes') }}</x-secondary-button>
            </form>

            <form method="POST" action="{{ route('two-factor.disable') }}" class="space-y-4 rounded-2xl border border-red-200 p-4 dark:border-red-900">
                @csrf
                @method('DELETE')
                <div>
                    <x-input-label for="two_factor_disable_password" :value="__('Current Password')" />
                    <x-text-input id="two_factor_disable_password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <x-danger-button onclick="return confirm('Disable two-factor authentication for this account?')">{{ __('Disable 2FA') }}</x-danger-button>
            </form>
        </div>
    @else
        @if ($user->two_factor_secret && $twoFactorQrSvg)
            <div class="mt-6 grid gap-6 lg:grid-cols-[240px_1fr]">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center dark:border-slate-800 dark:bg-slate-950">
                    {!! $twoFactorQrSvg !!}
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="font-bold text-slate-900 dark:text-white">Manual setup key</p>
                        <code class="mt-2 block break-all rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold text-slate-900 dark:bg-slate-950 dark:text-white">{{ $user->two_factor_secret }}</code>
                    </div>
                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="two_factor_code" :value="__('6-digit Authenticator Code')" />
                            <x-text-input id="two_factor_code" name="code" type="text" inputmode="numeric" class="mt-1 block w-full" placeholder="123456" required autofocus />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>
                        <x-primary-button>{{ __('Confirm and Enable 2FA') }}</x-primary-button>
                    </form>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('two-factor.start') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <x-input-label for="two_factor_start_password" :value="__('Current Password')" />
                    <x-text-input id="two_factor_start_password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <x-primary-button>{{ __('Set Up Authenticator App') }}</x-primary-button>
            </form>
        @endif
    @endif
</section>
