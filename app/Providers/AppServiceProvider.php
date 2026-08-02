<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Observers\ClientSubscriptionLimitObserver;
use App\Observers\SubscriptionRoleLimitObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(SuperAdminRouteServiceProvider::class);
    }

    public function boot(): void
    {
        foreach ([Client::class, Invoice::class, Payment::class, Quotation::class, Setting::class] as $model) {
            $model::observe(AuditableObserver::class);
        }

        User::observe(SubscriptionRoleLimitObserver::class);
        Client::observe(ClientSubscriptionLimitObserver::class);
    }
}
