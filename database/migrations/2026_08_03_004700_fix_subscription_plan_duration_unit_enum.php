<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE platform_subscription_plans MODIFY duration_unit ENUM('days','months','years') NOT NULL DEFAULT 'months'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('platform_subscription_plans')
                ->where('duration_unit', 'days')
                ->update(['duration_unit' => 'day']);
            DB::table('platform_subscription_plans')
                ->where('duration_unit', 'months')
                ->update(['duration_unit' => 'month']);
            DB::table('platform_subscription_plans')
                ->where('duration_unit', 'years')
                ->update(['duration_unit' => 'year']);

            DB::statement("ALTER TABLE platform_subscription_plans MODIFY duration_unit ENUM('day','month','year') NOT NULL DEFAULT 'month'");
        }
    }
};
