<?php

namespace App\Providers;

use App\Http\Controllers\SuperAdminCompanyController;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class SuperAdminRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'verified', '2fa', 'role:superadmin'])
            ->prefix('superadmin')
            ->name('superadmin.')
            ->group(function (): void {
                Route::get('/companies', [SuperAdminCompanyController::class, 'index'])->name('companies.index');
                Route::get('/companies/{company}', [SuperAdminCompanyController::class, 'show'])->name('companies.show');
                Route::put('/companies/{company}', [SuperAdminCompanyController::class, 'update'])->name('companies.update');
            });
    }
}
