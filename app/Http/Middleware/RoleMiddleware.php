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

        if (in_array($effectiveRole, $roles, true)) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
