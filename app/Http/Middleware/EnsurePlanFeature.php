<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! app()->bound('currentCompany')) {
            return $next($request);
        }

        $company = app('currentCompany');
        $subscription = $company->subscription()->with('plan')->first();

        abort_unless($subscription?->isActive() && $subscription->plan, 402, 'An active subscription is required.');
        abort_unless($subscription->plan->hasFeature($feature), 403, 'This feature is not included in the current subscription plan.');

        return $next($request);
    }
}
