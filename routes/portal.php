<?php

use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\CompanyOnboardingController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('portal.dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified', '2fa'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', [ClientPortalController::class, 'index'])->name('dashboard');

        Route::get('/companies/create', [CompanyOnboardingController::class, 'create'])
            ->name('companies.create');
        Route::post('/companies', [CompanyOnboardingController::class, 'store'])
            ->name('companies.store');
        Route::post('/companies/{company}/switch', [ClientPortalController::class, 'switch'])
            ->name('companies.switch');
    });

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', '2fa', 'company.selected'])
    ->name('dashboard');
