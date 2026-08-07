<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCompanySubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        // Company onboarding and billing must always remain reachable before a
        // subscription exists. Use URL paths as the primary guard because this
        // middleware runs globally and must not depend on route-name resolution
        // or central-domain configuration being correct.
        if ($request->is(
            'client-portal',
            'client-portal/*',
            'companies/create',
            'companies',
            'companies/*/switch'
        )) {
            return $next($request);
        }

        // The configured central domain is also always subscription-free.
        $host = strtolower($request->getHost());
        $centralDomain = strtolower((string) config('saas.central_domain'));

        if ($centralDomain !== '' && $host === $centralDomain) {
            return $next($request);
        }

        // Keep the named-route checks as a secondary safeguard.
        if ($request->routeIs(
            'client-portal.*',
            'companies.create',
            'companies.store',
            'companies.switch'
        )) {
            return $next($request);
        }

        /** @var Company|null $company */
        $company = $request->attributes->get('currentCompany');

        // Subscription enforcement is only meaningful inside a resolved
        // company workspace. Guests, central requests and superadmins pass.
        if (! $company || ! $request->user() || $request->user()->isSuperAdmin()) {
            return $next($request);
        }

        $subscription = $company->subscription;

        if ($subscription?->isActive()) {
            return $next($request);
        }

        $centralBase = sprintf(
            '%s://%s',
            config('saas.scheme', 'https'),
            config('saas.central_domain')
        );

        return response()->view('subscription.expired', [
            'company' => $company,
            'subscription' => $subscription,
            'billingUrl' => $centralBase.'/client-portal/companies/'.$company->id.'/billing',
            'portalUrl' => $centralBase.'/client-portal',
        ], 402);
    }
}
