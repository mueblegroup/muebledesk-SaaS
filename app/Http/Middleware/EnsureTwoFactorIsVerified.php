<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) return $next($request);

        $required = false;
        if (Schema::hasTable('platform_settings')) {
            $required = $user->isSuperAdmin()
                ? PlatformSetting::valueFor('auth.require_2fa_superadmin','1') === '1'
                : ($user->isAdmin() && PlatformSetting::valueFor('auth.require_2fa_company_admin','0') === '1');
        }

        if ($required && ! $user->hasTwoFactorEnabled()) {
            if ($request->routeIs('profile.*','two-factor.*','logout')) return $next($request);
            return redirect()->route('profile.edit')->with('warning','Two-factor authentication is required for your account. Complete setup to continue.');
        }

        if (! $user->hasTwoFactorEnabled()) return $next($request);
        if ($request->session()->get('two_factor_passed') === true) return $next($request);
        if ($request->routeIs('two-factor.*','logout')) return $next($request);

        return redirect()->route('two-factor.challenge');
    }
}
