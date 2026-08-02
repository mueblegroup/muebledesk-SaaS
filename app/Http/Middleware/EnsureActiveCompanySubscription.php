<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCompanySubscription
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        /** @var Company|null $company */
        $company = $request->attributes->get('currentCompany');

        if (! $company || ! $request->user()) {
            return $next($request);
        }

        if ($request->user()->isSuperAdmin()) {
            return $next($request);
        }

        $subscription = $company->subscription;

        if ($subscription?->isActive()) {
            return $next($request);
        }

        $billingUrl = sprintf(
            '%s://%s/client-portal/companies/%d/billing',
            config('saas.scheme', 'https'),
            config('saas.central_domain'),
            $company->id
        );

        return redirect()->away($billingUrl)
            ->with('error', 'Choose an active plan before opening this company workspace.');
    }
}
