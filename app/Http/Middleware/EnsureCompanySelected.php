<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs(
            'onboarding.company.*',
            'verification.*',
            'logout',
            'password.*'
        )) {
            return $next($request);
        }

        if (! $user->current_company_id) {
            return redirect()->route('onboarding.company.create');
        }

        if (! $user->companies()->whereKey($user->current_company_id)->exists()) {
            $user->forceFill(['current_company_id' => null])->save();

            return redirect()->route('onboarding.company.create')
                ->with('error', 'Please select or create a company before continuing.');
        }

        app()->instance('currentCompany', $user->currentCompany);

        return $next($request);
    }
}
