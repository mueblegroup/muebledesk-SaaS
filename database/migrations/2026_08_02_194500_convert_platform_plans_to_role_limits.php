<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_subscription_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_subscription_plans', 'price')) {
                $table->decimal('price', 12, 2)->default(0)->after('description');
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'duration_value')) {
                $table->unsignedInteger('duration_value')->default(1)->after('currency');
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'duration_unit')) {
                $table->enum('duration_unit', ['day', 'month', 'year'])->default('month')->after('duration_value');
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'admin_limit')) {
                $table->unsignedInteger('admin_limit')->nullable()->after('duration_unit');
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'employee_limit')) {
                $table->unsignedInteger('employee_limit')->nullable()->after('admin_limit');
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'client_limit')) {
                $table->unsignedInteger('client_limit')->nullable()->after('employee_limit');
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'auto_renew_default')) {
                $table->boolean('auto_renew_default')->default(true)->after('client_limit');
            }
        });

        Schema::table('company_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_subscriptions', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('company_subscriptions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
            }
            if (! Schema::hasColumn('company_subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(true)->after('ends_at');
            }
            if (! Schema::hasColumn('company_subscriptions', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('auto_renew');
            }
            if (! Schema::hasColumn('company_subscriptions', 'renewal_failures')) {
                $table->unsignedInteger('renewal_failures')->default(0)->after('is_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'ends_at', 'auto_renew', 'is_enabled', 'renewal_failures']);
        });

        Schema::table('platform_subscription_plans', function (Blueprint $table): void {
            $table->dropColumn(['price', 'duration_value', 'duration_unit', 'admin_limit', 'employee_limit', 'client_limit', 'auto_renew_default']);
        });
    }
};
