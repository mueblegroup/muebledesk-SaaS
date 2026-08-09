<?php

namespace App\Http\Controllers;

use App\Providers\RouteServiceProvider;
use App\Services\ActivityLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorAuthenticationController extends Controller
{
    public function challenge(): View|RedirectResponse
    {
        if (! Auth::user()?->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        if (session('two_factor_passed') === true) {
            return $this->postAuthenticationRedirect();
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, TwoFactorService $twoFactorService, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->validate([
            'code' => 'nullable|string|max:20',
            'recovery_code' => 'nullable|string|max:30',
        ]);

        $user = Auth::user();

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        $valid = false;
        $usedRecoveryCode = false;

        if ($request->filled('code')) {
            $valid = $twoFactorService->verifyCode($user->two_factor_secret, $request->input('code'));
        }

        if (! $valid && $request->filled('recovery_code')) {
            $valid = $twoFactorService->useRecoveryCode($user, $request->input('recovery_code'));
            $usedRecoveryCode = $valid;
        }

        if (! $valid) {
            $activityLogger->log('security.two_factor_failed', 'Invalid two-factor challenge attempt', $user);

            throw ValidationException::withMessages([
                'code' => 'The authentication code or recovery code is invalid.',
            ]);
        }

        $request->session()->put('two_factor_passed', true);
        $activityLogger->log($usedRecoveryCode ? 'security.two_factor_recovery_used' : 'security.two_factor_passed', 'Two-factor challenge passed', $user);
        $activityLogger->log('security.login', 'User logged in', $user);

        return $this->postAuthenticationRedirect();
    }

    public function start(Request $request, TwoFactorService $twoFactorService, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->with('status', 'two-factor-already-enabled');
        }

        $user->forceFill([
            'two_factor_secret' => $twoFactorService->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_enabled_at' => null,
        ])->save();

        $activityLogger->log('security.two_factor_setup_started', 'Two-factor setup started', $user);

        return back()->with('status', 'two-factor-setup-started');
    }

    public function confirm(Request $request, TwoFactorService $twoFactorService, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return back()->with('error', 'Start two-factor setup first.');
        }

        if (! $twoFactorService->verifyCode($user->two_factor_secret, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid.',
            ]);
        }

        $recoveryCodes = $twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $twoFactorService->hashRecoveryCodes($recoveryCodes),
            'two_factor_enabled_at' => now(),
        ])->save();

        $request->session()->put('two_factor_passed', true);
        $request->session()->flash('two_factor_recovery_codes_plain', $recoveryCodes);
        $activityLogger->log('security.two_factor_enabled', 'Two-factor authentication enabled', $user);

        return back()->with('status', 'two-factor-enabled');
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorService $twoFactorService, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        abort_unless($user->hasTwoFactorEnabled(), 403);

        $recoveryCodes = $twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $twoFactorService->hashRecoveryCodes($recoveryCodes),
        ])->save();

        $request->session()->flash('two_factor_recovery_codes_plain', $recoveryCodes);
        $activityLogger->log('security.two_factor_recovery_regenerated', 'Two-factor recovery codes regenerated', $user);

        return back()->with('status', 'two-factor-recovery-regenerated');
    }

    public function disable(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled_at' => null,
        ])->save();

        $request->session()->put('two_factor_passed', true);
        $activityLogger->log('security.two_factor_disabled', 'Two-factor authentication disabled', $user);

        return back()->with('status', 'two-factor-disabled');
    }

    private function postAuthenticationRedirect(): RedirectResponse
    {
        $user = Auth::user();

        if ($user?->isSuperAdmin()) {
            return redirect()->intended(route('superadmin.dashboard'));
        }

        if (request()->attributes->has('currentCompany')) {
            if ($user?->isCustomer() && ! $user->profile_completed_at) {
                return redirect()->route('profile.edit');
            }

            return redirect()->intended(route('dashboard'));
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
