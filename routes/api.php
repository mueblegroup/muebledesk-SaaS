<?php

use App\Http\Controllers\Api\V1\ClientApiController;
use App\Http\Controllers\Api\V1\ExpenseApiController;
use App\Http\Controllers\Api\V1\InvoiceApiController;
use App\Http\Controllers\Api\V1\SimpleResourceApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => 'v1']));

    Route::middleware('api.key:clients.read')->group(function () {
        Route::get('/clients', [ClientApiController::class, 'index']);
        Route::get('/clients/{client}', [ClientApiController::class, 'show']);
    });
    Route::post('/clients', [ClientApiController::class, 'store'])->middleware('api.key:clients.write');
    Route::match(['put', 'patch'], '/clients/{client}', [ClientApiController::class, 'update'])->middleware('api.key:clients.write');
    Route::delete('/clients/{client}', [ClientApiController::class, 'destroy'])->middleware('api.key:clients.delete');

    Route::middleware('api.key:invoices.read')->group(function () {
        Route::get('/invoices', [InvoiceApiController::class, 'index']);
        Route::get('/invoices/check-duplicate', [InvoiceApiController::class, 'checkDuplicate']);
        Route::get('/invoices/{invoice}', [InvoiceApiController::class, 'show']);
    });
    Route::post('/invoices', [InvoiceApiController::class, 'store'])->middleware('api.key:invoices.write');
    Route::match(['put', 'patch'], '/invoices/{invoice}', [InvoiceApiController::class, 'update'])->middleware('api.key:invoices.write');
    Route::delete('/invoices/{invoice}', [InvoiceApiController::class, 'destroy'])->middleware('api.key:invoices.delete');
    Route::post('/invoices/{invoice}/payments', [InvoiceApiController::class, 'recordPayment'])->middleware('api.key:payments.write');

    Route::middleware('api.key:quotations.read')->group(function () {
        Route::get('/quotations', [SimpleResourceApiController::class, 'quotations']);
        Route::get('/quotations/{quotation}', [SimpleResourceApiController::class, 'showQuotation']);
    });
    Route::post('/quotations', [SimpleResourceApiController::class, 'storeQuotation'])->middleware('api.key:quotations.write');
    Route::match(['put', 'patch'], '/quotations/{quotation}', [SimpleResourceApiController::class, 'updateQuotation'])->middleware('api.key:quotations.write');
    Route::delete('/quotations/{quotation}', [SimpleResourceApiController::class, 'deleteQuotation'])->middleware('api.key:quotations.delete');

    Route::middleware('api.key:payments.read')->group(function () {
        Route::get('/payments', [SimpleResourceApiController::class, 'payments']);
        Route::get('/payments/{payment}', [SimpleResourceApiController::class, 'showPayment']);
    });
    Route::delete('/payments/{payment}', [SimpleResourceApiController::class, 'deletePayment'])->middleware('api.key:payments.delete');

    Route::middleware('api.key:expenses.read')->group(function () {
        Route::get('/expenses', [ExpenseApiController::class, 'index']);
        Route::get('/expenses/{expense}', [ExpenseApiController::class, 'show']);
    });
    Route::post('/expenses', [ExpenseApiController::class, 'store'])->middleware('api.key:expenses.write');
    Route::match(['put', 'patch'], '/expenses/{expense}', [ExpenseApiController::class, 'update'])->middleware('api.key:expenses.write');
    Route::delete('/expenses/{expense}', [ExpenseApiController::class, 'destroy'])->middleware('api.key:expenses.delete');
    Route::get('/reports/profit-loss', [ExpenseApiController::class, 'profitLoss'])->middleware('api.key:reports.profit_loss');

    Route::middleware('api.key:recurring_invoices.read')->group(function () {
        Route::get('/recurring-invoices', [SimpleResourceApiController::class, 'recurringInvoices']);
        Route::get('/recurring-invoices/{recurringInvoice}', [SimpleResourceApiController::class, 'showRecurringInvoice']);
    });
    Route::post('/recurring-invoices', [SimpleResourceApiController::class, 'storeRecurringInvoice'])->middleware('api.key:recurring_invoices.write');
    Route::match(['put', 'patch'], '/recurring-invoices/{recurringInvoice}', [SimpleResourceApiController::class, 'updateRecurringInvoice'])->middleware('api.key:recurring_invoices.write');
    Route::delete('/recurring-invoices/{recurringInvoice}', [SimpleResourceApiController::class, 'deleteRecurringInvoice'])->middleware('api.key:recurring_invoices.delete');

    Route::get('/users', [SimpleResourceApiController::class, 'users'])->middleware('api.key:users.read');
    Route::post('/users', [SimpleResourceApiController::class, 'storeUser'])->middleware('api.key:users.write');
    Route::match(['put', 'patch'], '/users/{user}', [SimpleResourceApiController::class, 'updateUser'])->middleware('api.key:users.write');

    Route::get('/settings', [SimpleResourceApiController::class, 'settings'])->middleware('api.key:settings.read');
    Route::get('/activity-logs', [SimpleResourceApiController::class, 'activityLogs'])->middleware('api.key:activity_logs.read');
});
