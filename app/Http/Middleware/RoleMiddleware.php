<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\UserRoleEnum; // <--- Make sure you're importing your UserRoleEnum

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string ...$roles  // PHP 8+ allows variadic args directly, implies strings
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect('login'); // Not authenticated, redirect to login
        }

        // Get the user's role as an Enum object
        $userRole = auth()->user()->role;

        // Iterate through the required roles (which are strings from the route definition)
        foreach ($roles as $requiredRoleString) {
            // Compare the Enum object's VALUE with the string from the route
            if ($userRole->value === $requiredRoleString) {
                return $next($request); // User has one of the required roles
            }
        }

        // If user does not have any of the required roles after checking all
        abort(403, 'Unauthorized action.');
    }
}
