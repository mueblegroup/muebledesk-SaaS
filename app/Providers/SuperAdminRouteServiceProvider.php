<?php

namespace App\Providers;

use App\Http\Controllers\ClientPortalBillingController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\SystemGuideController;
use App\Http\Controllers\SuperAdmin\CompanySubscriptionController;
use App\Http\Controllers\SuperAdmin\PlatformController;
use App\Http\Controllers\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\SuperAdminCompanyController;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class SuperAdminRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/', [MarketingController::class, 'home'])->name('marketing.home');
            Route::get('/features', [MarketingController::class, 'features'])->name('marketing.features');
            Route::get('/how-it-works', [MarketingController::class, 'howItWorks'])->name('marketing.how-it-works');
            Route::get('/security', [MarketingController::class, 'security'])->name('marketing.security');
            Route::get('/pricing', [MarketingController::class, 'pricing'])->name('marketing.pricing');
            Route::get('/contact', [MarketingController::class, 'contact'])->name('marketing.contact');
        });

        Route::middleware(['web', 'auth', 'verified', '2fa', 'role:superadmin'])
            ->prefix('superadmin')
            ->name('superadmin.')
            ->group(function (): void {
                Route::get('/companies', [SuperAdminCompanyController::class, 'index'])->name('companies.index');
                Route::get('/companies/{company}', [SuperAdminCompanyController::class, 'show'])->name('companies.show');
                Route::put('/companies/{company}', [SuperAdminCompanyController::class, 'update'])->name('companies.update');
                Route::put('/companies/{company}/subscription', [CompanySubscriptionController::class, 'update'])->name('companies.subscription.update');

                Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index'])->name('subscription-plans.index');
                Route::post('/subscription-plans', [SubscriptionPlanController::class, 'store'])->name('subscription-plans.store');
                Route::put('/subscription-plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('subscription-plans.update');
                Route::delete('/subscription-plans/{plan}', [SubscriptionPlanController::class, 'destroy'])->name('subscription-plans.destroy');

                Route::get('/payments', [PlatformController::class, 'payments'])->name('payments.index');
                Route::get('/settings', [PlatformController::class, 'settings'])->name('settings.index');
                Route::put('/settings', [PlatformController::class, 'updateSettings'])->name('settings.update');
            });

        Route::middleware(['web', 'auth', 'verified', '2fa'])->group(function (): void {
            Route::patch('/client-portal/companies/{company}/billing/auto-renew', [ClientPortalBillingController::class, 'toggleAutoRenew'])
                ->name('client-portal.billing.auto-renew');
            Route::get('/client-portal/system-guide', [SystemGuideController::class, 'clientPortal'])
                ->name('client-portal.system-guide');
        });
    }
}
