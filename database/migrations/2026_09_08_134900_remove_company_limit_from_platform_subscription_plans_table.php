<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_subscription_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_subscription_plans', 'company_limit')) {
                $table->dropColumn('company_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_subscription_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_subscription_plans', 'company_limit')) {
                $table->unsignedInteger('company_limit')->nullable()->after('duration_unit');
            }
        });
    }
};
