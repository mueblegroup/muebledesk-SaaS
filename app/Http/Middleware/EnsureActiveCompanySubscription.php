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
