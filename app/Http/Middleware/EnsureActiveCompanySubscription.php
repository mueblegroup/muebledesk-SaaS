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
        // The central client portal must always remain reachable so a new user
        // can create a company, choose a plan, pay, and manage billing.
        $host = strtolower($request->getHost());
        $centralDomain = strtolower((string) config('saas.central_domain'));

        if ($host === $centralDomain) {
            return $next($request);
        }

        // These routes are deliberately accessible before a subscription exists.
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
