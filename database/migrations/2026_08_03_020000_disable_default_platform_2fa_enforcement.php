<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        DB::table('platform_settings')
            ->whereIn('key', [
                'auth.require_2fa_superadmin',
                'auth.require_2fa_company_admin',
            ])
            ->update([
                'value' => '0',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally do not re-enable mandatory 2FA during rollback.
    }
};
