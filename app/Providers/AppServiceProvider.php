<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Observers\ClientSubscriptionLimitObserver;
use App\Observers\SubscriptionRoleLimitObserver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(SuperAdminRouteServiceProvider::class);
    }

    public function boot(): void
    {
        if (Schema::hasTable('platform_settings')) {
            foreach (['google','microsoft','apple'] as $provider) {
                foreach (['client_id','client_secret','redirect'] as $field) {
                    $value = PlatformSetting::valueFor("sso.{$provider}_{$field}");
                    if (filled($value)) config(["services.{$provider}.{$field}" => $value]);
                }
            }
            $tenant = PlatformSetting::valueFor('sso.microsoft_tenant');
            if (filled($tenant)) config(['services.microsoft.tenant' => $tenant]);
            $platformName = PlatformSetting::valueFor('platform.name');
            if (filled($platformName)) config(['app.name' => $platformName]);
        }

        foreach ([Client::class, Invoice::class, Payment::class, Quotation::class, Setting::class] as $model) {
            $model::observe(AuditableObserver::class);
        }

        User::observe(SubscriptionRoleLimitObserver::class);
        Client::observe(ClientSubscriptionLimitObserver::class);
    }
}
