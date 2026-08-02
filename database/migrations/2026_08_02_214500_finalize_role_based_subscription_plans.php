<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_subscription_plans', 'price')) {
                $table->decimal('price', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'duration_value')) {
                $table->unsignedInteger('duration_value')->default(1);
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'duration_unit')) {
                $table->string('duration_unit', 10)->default('months');
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'admin_limit')) {
                $table->unsignedInteger('admin_limit')->nullable();
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'employee_limit')) {
                $table->unsignedInteger('employee_limit')->nullable();
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'client_limit')) {
                $table->unsignedInteger('client_limit')->nullable();
            }
            if (! Schema::hasColumn('platform_subscription_plans', 'auto_renew_default')) {
                $table->boolean('auto_renew_default')->default(true);
            }
        });

        Schema::table('company_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('company_subscriptions', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
            if (! Schema::hasColumn('company_subscriptions', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->index();
            }
            if (! Schema::hasColumn('company_subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(true);
            }
            if (! Schema::hasColumn('company_subscriptions', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true);
            }
            if (! Schema::hasColumn('company_subscriptions', 'renewal_failure_count')) {
                $table->unsignedInteger('renewal_failure_count')->default(0);
            }
            if (! Schema::hasColumn('company_subscriptions', 'last_renewal_attempt_at')) {
                $table->timestamp('last_renewal_attempt_at')->nullable();
            }
            if (! Schema::hasColumn('company_subscriptions', 'last_renewal_error')) {
                $table->text('last_renewal_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        // New subscription fields are intentionally retained to avoid data loss.
    }
};
