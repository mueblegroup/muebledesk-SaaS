<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->current_company_id) {
            return redirect()->route('portal.dashboard')
                ->with('error', 'Create or select a company before opening its workspace.');
        }

        if (! $request->user()->companies()->whereKey($request->user()->current_company_id)->exists()) {
            $request->user()->forceFill(['current_company_id' => null])->save();

            return redirect()->route('portal.dashboard')
                ->with('error', 'Your selected company is no longer available.');
        }

        $userRole = $request->user()->role;

        foreach ($roles as $requiredRole) {
            if ($userRole->value === $requiredRole) {
                app()->instance('currentCompany', $request->user()->currentCompany);

                return $next($request);
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
