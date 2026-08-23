<?php

use App\Http\Controllers\PublicDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['signed', 'throttle:60,1'])
    ->prefix('shared')
    ->name('shared.')
    ->group(function () {
        Route::get('/invoices/{invoice}', [PublicDocumentController::class, 'invoice'])->name('invoice');
        Route::get('/quotations/{quotation}', [PublicDocumentController::class, 'quotation'])->name('quotation');
        Route::get('/payments/{payment}', [PublicDocumentController::class, 'payment'])->name('payment');
    });
