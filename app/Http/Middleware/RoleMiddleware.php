<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Authorize against the effective workspace role on tenant subdomains.
     * Superadmin remains an account-level central role; normal workspace roles
     * are never valid without a resolved tenant company.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect('login');
        }

        $workspaceRoles = ['admin', 'employee', 'customer'];
        $requiresTenant = count(array_intersect($roles, $workspaceRoles)) > 0;

        if ($requiresTenant && ! app()->bound('currentCompany')) {
            abort(403, 'Company workspace access is only available through the company subdomain.');
        }

        $effectiveRole = auth()->user()->workspaceRole();

        if ($effectiveRole === 'customer' && in_array('customer', $roles, true) && app()->bound('currentCompany')) {
            $company = app('currentCompany');
            $subscription = $company->subscription()->with('plan')->first();

            abort_unless($subscription?->isActive() && $subscription->plan, 402, 'An active subscription is required.');
            abort_unless($subscription->plan->hasFeature('customer_portal'), 403, 'The customer portal is not included in the current subscription plan.');
        }

        if (in_array($effectiveRole, $roles, true)) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
