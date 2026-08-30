<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // stripe_product_id and stripe_price_id are part of the original SaaS
        // billing schema (2026_08_02_150000_create_saas_billing_tables). Do not
        // recreate them here. Column guards also make this migration safe to
        // retry if a previous deployment stopped part-way through.
        if (! Schema::hasColumn('platform_subscription_plans', 'billing_rank')) {
            Schema::table('platform_subscription_plans', function (Blueprint $table): void {
                $table->unsignedInteger('billing_rank')->default(0)->after('sort_order');
            });
        }

        if (! Schema::hasColumn('company_subscriptions', 'pending_platform_subscription_plan_id')) {
            Schema::table('company_subscriptions', function (Blueprint $table): void {
                $table->foreignId('pending_platform_subscription_plan_id')
                    ->nullable()
                    ->after('platform_subscription_plan_id')
                    ->constrained('platform_subscription_plans')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('company_subscriptions', 'pending_plan_effective_at')) {
            Schema::table('company_subscriptions', function (Blueprint $table): void {
                $table->timestamp('pending_plan_effective_at')->nullable()->after('current_period_ends_at');
            });
        }

        if (! Schema::hasColumn('company_subscriptions', 'stripe_subscription_schedule_id')) {
            Schema::table('company_subscriptions', function (Blueprint $table): void {
                $table->string('stripe_subscription_schedule_id')
                    ->nullable()
                    ->after('stripe_subscription_id')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_subscriptions', 'pending_platform_subscription_plan_id')) {
            Schema::table('company_subscriptions', function (Blueprint $table): void {
                $table->dropForeign(['pending_platform_subscription_plan_id']);
                $table->dropColumn('pending_platform_subscription_plan_id');
            });
        }

        if (Schema::hasColumn('company_subscriptions', 'pending_plan_effective_at')) {
            Schema::table('company_subscriptions', function (Blueprint $table): void {
                $table->dropColumn('pending_plan_effective_at');
            });
        }

        if (Schema::hasColumn('company_subscriptions', 'stripe_subscription_schedule_id')) {
            Schema::table('company_subscriptions', function (Blueprint $table): void {
                $table->dropColumn('stripe_subscription_schedule_id');
            });
        }

        if (Schema::hasColumn('platform_subscription_plans', 'billing_rank')) {
            Schema::table('platform_subscription_plans', function (Blueprint $table): void {
                $table->dropColumn('billing_rank');
            });
        }
    }
};
