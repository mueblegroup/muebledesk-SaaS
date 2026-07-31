<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        if (Auth::user()?->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor_passed', false);
            $activityLogger->log('security.two_factor_challenge', 'Two-factor challenge required', Auth::user());

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->put('two_factor_passed', true);
        $activityLogger->log('security.login', 'User logged in', Auth::user());

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $activityLogger->log('security.logout', 'User logged out', Auth::user());
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
