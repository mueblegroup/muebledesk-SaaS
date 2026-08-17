<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        if (! app()->bound('currentCompany')) {
            return $next($request);
        }

        $feature ??= $this->featureForRequest($request);

        if (! $feature) {
            return $next($request);
        }

        $company = app('currentCompany');
        $subscription = $company->subscription()->with('plan')->first();

        abort_unless($subscription?->isActive() && $subscription->plan, 402, 'An active subscription is required.');
        abort_unless($subscription->plan->hasFeature($feature), 403, 'This feature is not included in the current subscription plan.');

        return $next($request);
    }

    private function featureForRequest(Request $request): ?string
    {
        $routeName = (string) optional($request->route())->getName();

        if ($this->matches($routeName, ['reports.profit_loss', 'reports.profit_loss.*', 'expenses.profit_loss'])) {
            return 'profit_loss';
        }

        if ($this->matches($routeName, ['expenses.*'])) {
            return 'expenses';
        }

        if ($this->matches($routeName, ['recurring-invoices.*'])) {
            return 'recurring_invoices';
        }

        if ($this->matches($routeName, [
            'einvoices.*',
            'customer.einvoices.*',
            'customer.einvoice-profile.*',
            'admin.einvoice-settings.*',
            'myinvois.*',
        ])) {
            return 'einvoice';
        }

        if ($this->matches($routeName, ['admin.api-keys.*', 'admin.api-guide.*'])) {
            return 'api_access';
        }

        if ($this->matches($routeName, [
            'invoices.customer_index',
            'invoices.customer_export',
            'invoices.customer_show',
            'invoices.customer_download',
        ])) {
            return 'customer_portal';
        }

        return null;
    }

    private function matches(string $routeName, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $routeName) {
                return true;
            }

            if (str_ends_with($pattern, '*') && str_starts_with($routeName, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
