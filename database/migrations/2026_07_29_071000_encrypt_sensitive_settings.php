<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $sensitiveKeys = [
        'hitpay_api_key',
        'hitpay_salt',
        'hitpay_webhook_salt',
        'stripe_secret_key',
        'stripe_webhook_secret',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->whereIn('key', $this->sensitiveKeys)
            ->whereNotNull('value')
            ->orderBy('id')
            ->get(['id', 'value'])
            ->each(function ($setting) {
                $value = (string) $setting->value;
                if ($value === '' || str_starts_with($value, 'enc:')) {
                    return;
                }

                DB::table('settings')
                    ->where('id', $setting->id)
                    ->update(['value' => 'enc:'.Crypt::encryptString($value)]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->whereIn('key', $this->sensitiveKeys)
            ->whereNotNull('value')
            ->orderBy('id')
            ->get(['id', 'value'])
            ->each(function ($setting) {
                $value = (string) $setting->value;
                if (! str_starts_with($value, 'enc:')) {
                    return;
                }

                try {
                    DB::table('settings')
                        ->where('id', $setting->id)
                        ->update(['value' => Crypt::decryptString(substr($value, 4))]);
                } catch (\Throwable) {
                    // Leave the encrypted value untouched if the application key changed.
                }
            });
    }
};
