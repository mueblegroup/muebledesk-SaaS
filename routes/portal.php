<?php

use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\CompanyOnboardingController;
use Illuminate\Support\Facades\Route;

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
