<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Authorize against the effective workspace role on tenant subdomains.
     * On the central SaaS domain this falls back to the account-level role.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect('login');
        }

        $effectiveRole = auth()->user()->workspaceRole();

        if (in_array($effectiveRole, $roles, true)) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
