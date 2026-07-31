<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CustomerEInvoiceProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EInvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MyInvoisTaxpayerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPaymentController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SystemGuideController;
use App\Http\Controllers\TwoFactorAuthenticationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Webhook\HitPayWebhookController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Http\Middleware\NormalizeClientCountryCode;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::post('/hitpay/webhook', [HitPayWebhookController::class, 'handle'])->name('hitpay.webhook');
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
Route::get('/payment/confirmation', [PublicPaymentController::class, 'confirmation'])->name('payment.confirmation');

Route::middleware('auth')->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorAuthenticationController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorAuthenticationController::class, 'verify'])->middleware('throttle:6,1')->name('two-factor.verify');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', '2fa'])
    ->name('dashboard');

Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/system-guide', [SystemGuideController::class, 'index'])->name('system-guide.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/business-details', [ProfileController::class, 'updateBusinessDetails'])->name('profile.business-details.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/profile/two-factor/start', [TwoFactorAuthenticationController::class, 'start'])->name('two-factor.start');
    Route::post('/profile/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->middleware('throttle:6,1')->name('two-factor.confirm');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
    Route::delete('/profile/two-factor', [TwoFactorAuthenticationController::class, 'disable'])->name('two-factor.disable');

    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'downloadReceipt'])->name('payments.receipt');
    Route::post('/myinvois/taxpayers/search', [MyInvoisTaxpayerController::class, 'search'])
        ->middleware('throttle:10,1')
        ->name('myinvois.taxpayers.search');
});

Route::middleware(['auth', '2fa', 'role:admin'])->group(function () {
    Route::redirect('/admin/dashboard', '/dashboard')->name('admin.dashboard');

    Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
    Route::post('/users/bulk-delete', [UserController::class, 'bulkDestroy'])->name('users.bulk_destroy');
    Route::resource('users', UserController::class)->except(['show']);

    Route::get('/admin/settings', [AdminSettingController::class, 'index'])->name('admin.setting.index');
    Route::put('/admin/settings', [AdminSettingController::class, 'update'])->name('admin.setting.update');
    Route::get('/admin/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
    Route::get('/admin/api-keys', [ApiKeyController::class, 'index'])->name('admin.api-keys.index');
    Route::post('/admin/api-keys', [ApiKeyController::class, 'store'])->name('admin.api-keys.store');
    Route::patch('/admin/api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])->name('admin.api-keys.revoke');
    Route::delete('/admin/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('admin.api-keys.destroy');
});

Route::middleware(['auth', '2fa', 'role:admin,employee'])->group(function () {
    Route::redirect('/employee/dashboard', '/dashboard')->name('employee.dashboard');

    Route::get('/clients/export', [ClientController::class, 'export'])->name('clients.export');
    Route::post('/clients/bulk-delete', [ClientController::class, 'bulkDestroy'])->name('clients.bulk_destroy');
    Route::post('/clients/quick-store', [ClientController::class, 'quickStore'])->name('clients.quick_store');
    Route::post('/clients/{client}/send-password-setup-link', [ClientController::class, 'sendPasswordSetupLink'])->name('clients.send_password_setup_link');
    Route::resource('clients', ClientController::class)->middleware(NormalizeClientCountryCode::class);

    Route::get('/quotations/export', [QuotationController::class, 'export'])->name('quotations.export');
    Route::post('/quotations/bulk-delete', [QuotationController::class, 'bulkDestroy'])->name('quotations.bulk_destroy');
    Route::resource('quotations', QuotationController::class);
    Route::get('/quotations/{quotation}/download', [QuotationController::class, 'downloadPdf'])->name('quotations.download');

    Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::post('/invoices/bulk-delete', [InvoiceController::class, 'bulkDestroy'])->name('invoices.bulk_destroy');
    Route::resource('invoices', InvoiceController::class);
    Route::get('/invoices/create-from-quotation/{quotation}', [InvoiceController::class, 'createFromQuotation'])->name('invoices.create_from_quotation');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'downloadPdf'])->name('invoices.download');
    Route::resource('invoices.payments', PaymentController::class)->only(['create', 'store']);

    Route::redirect('/einvoices', '/invoices')->name('einvoices.index');
    Route::get('/invoices/{invoice}/einvoice', [EInvoiceController::class, 'preview'])->name('einvoices.preview');
    Route::post('/invoices/{invoice}/einvoice/submit', [EInvoiceController::class, 'submit'])->middleware('throttle:5,1')->name('einvoices.submit');
    Route::post('/invoices/{invoice}/einvoice/refresh', [EInvoiceController::class, 'refresh'])->middleware('throttle:30,1')->name('einvoices.refresh');
    Route::put('/invoices/{invoice}/einvoice/cancel', [EInvoiceController::class, 'cancel'])->middleware('throttle:3,1')->name('einvoices.cancel');

    Route::get('/payments/export', [PaymentController::class, 'export'])->name('payments.export');
    Route::get('/payments/create', [PaymentController::class, 'manualCreate'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'manualStore'])->name('payments.store');
    Route::resource('payments', PaymentController::class)->only(['index', 'show', 'edit', 'update', 'destroy']);

    Route::get('/expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
    Route::get('/expenses/profit-loss', [ExpenseController::class, 'profitLoss'])->name('expenses.profit_loss');
    Route::resource('expenses', ExpenseController::class);

    Route::get('/recurring-invoices/export', [RecurringInvoiceController::class, 'export'])->name('recurring-invoices.export');
    Route::post('/recurring-invoices/bulk-delete', [RecurringInvoiceController::class, 'bulkDestroy'])->name('recurring-invoices.bulk_destroy');
    Route::resource('recurring-invoices', RecurringInvoiceController::class);
    Route::get('invoices/{invoice}/create-recurring', [RecurringInvoiceController::class, 'createFromInvoice'])->name('recurring-invoices.create-from-invoice');
    Route::post('invoices/{invoice}/store-recurring', [RecurringInvoiceController::class, 'storeFromInvoice'])->name('recurring-invoices.store-from-invoice');
    Route::post('recurring-invoices/{recurringInvoice}/toggle-active', [RecurringInvoiceController::class, 'toggleActive'])->name('recurring-invoices.toggle-active');
});

Route::middleware(['auth', '2fa', 'role:customer'])->group(function () {
    Route::redirect('/customer/dashboard', '/dashboard')->name('customer.dashboard');
    Route::get('/my-einvoice-profile', [CustomerEInvoiceProfileController::class, 'edit'])->name('customer.einvoice-profile.edit');
    Route::put('/my-einvoice-profile', [CustomerEInvoiceProfileController::class, 'update'])->name('customer.einvoice-profile.update');
    Route::get('/my-invoices/export', [InvoiceController::class, 'customerExport'])->name('invoices.customer_export');
    Route::get('/my-invoices', [InvoiceController::class, 'customerIndex'])->name('invoices.customer_index');
    Route::get('/my-invoices/{invoice}', [InvoiceController::class, 'customerShow'])->name('invoices.customer_show');
    Route::get('/my-invoices/{invoice}/download', [InvoiceController::class, 'customerDownloadPdf'])->name('invoices.customer_download');
    Route::get('/my-invoices/{invoice}/einvoice', [EInvoiceController::class, 'preview'])->name('customer.einvoices.preview');
    Route::post('/my-invoices/{invoice}/einvoice/submit', [EInvoiceController::class, 'submit'])->middleware('throttle:3,1')->name('customer.einvoices.submit');
    Route::post('/my-invoices/{invoice}/einvoice/refresh', [EInvoiceController::class, 'refresh'])->middleware('throttle:20,1')->name('customer.einvoices.refresh');
});

require __DIR__.'/auth.php';
