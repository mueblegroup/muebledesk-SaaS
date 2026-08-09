<?php

namespace App\Providers;

use App\Http\Controllers\Admin\ApiGuideController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The central SaaS client portal is the post-authentication destination.
     */
    public const HOME = '/client-portal';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware(['web', 'auth', 'verified', '2fa', 'role:admin', 'plan.feature:api_access'])
                ->get('/admin/api-guide', [ApiGuideController::class, 'index'])
                ->name('admin.api-guide.index');

            // The public marketing page belongs exclusively to the configured
            // SaaS root domain. Every other hostname (including valid company
            // subdomains) is treated as an application/workspace host.
            Route::middleware('web')->get('/', function (Request $request) {
                $host = strtolower(rtrim($request->getHost(), '.'));
                $rootDomain = strtolower(rtrim((string) config('saas.root_domain'), '.'));
                $isMarketingHost = $rootDomain !== '' && hash_equals($rootDomain, $host);

                if (! $isMarketingHost) {
                    return auth()->check()
                        ? redirect()->route('dashboard')
                        : redirect()->route('login');
                }

                if (! auth()->check()) {
                    return view('landing');
                }

                return auth()->user()->isSuperAdmin()
                    ? redirect()->route('superadmin.dashboard')
                    : redirect()->route('client-portal.dashboard');
            })->name('landing');
        });
    }
}
