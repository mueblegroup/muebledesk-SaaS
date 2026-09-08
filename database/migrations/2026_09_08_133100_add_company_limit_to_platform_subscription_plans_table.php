<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_subscription_plans', function (Blueprint $table): void {
            $table->unsignedInteger('company_limit')->nullable()->after('duration_unit');
        });
    }

    public function down(): void
    {
        Schema::table('platform_subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('company_limit');
        });
    }
};
