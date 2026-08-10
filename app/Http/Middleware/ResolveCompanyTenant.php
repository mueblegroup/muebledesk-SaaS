<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $centralDomain = strtolower((string) config('saas.central_domain'));
        $rootDomain = strtolower((string) config('saas.root_domain'));

        if ($host === $centralDomain) {
            return $next($request);
        }

        $suffix = '.'.$rootDomain;

        if (! str_ends_with($host, $suffix)) {
            abort(404);
        }

        $slug = substr($host, 0, -strlen($suffix));

        if ($slug === '' || str_contains($slug, '.')) {
            abort(404);
        }

        $company = Company::query()->where('slug', $slug)->firstOrFail();

        app()->instance(Company::class, $company);
        app()->instance('currentCompany', $company);
        $request->attributes->set('currentCompany', $company);

        config([
            'myinvois.enabled' => filter_var(Setting::get('myinvois_enabled', '0'), FILTER_VALIDATE_BOOL),
            'myinvois.environment' => (string) Setting::get('myinvois_environment', 'sandbox'),
        ]);

        if ($request->user()) {
            $user = $request->user();
            $workspaceRole = $user->workspaceRole($company);

            abort_unless(
                in_array($workspaceRole, ['admin', 'employee', 'customer'], true),
                403,
                'You do not have access to this company workspace.'
            );

            if ((int) $user->current_company_id !== (int) $company->getKey()) {
                $user->forceFill([
                    'current_company_id' => $company->getKey(),
                ])->save();
            }
        }

        return $next($request);
    }
}
