<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_subscription_plans', function (Blueprint $table): void {
            $table->unsignedInteger('billing_rank')->default(0)->after('sort_order');
            $table->string('stripe_product_id')->nullable()->after('billing_rank')->index();
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id')->index();
        });

        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->foreignId('pending_platform_subscription_plan_id')
                ->nullable()
                ->after('platform_subscription_plan_id')
                ->constrained('platform_subscription_plans')
                ->nullOnDelete();
            $table->timestamp('pending_plan_effective_at')->nullable()->after('current_period_ends_at');
            $table->string('stripe_subscription_schedule_id')->nullable()->after('stripe_subscription_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->dropForeign(['pending_platform_subscription_plan_id']);
            $table->dropColumn([
                'pending_platform_subscription_plan_id',
                'pending_plan_effective_at',
                'stripe_subscription_schedule_id',
            ]);
        });

        Schema::table('platform_subscription_plans', function (Blueprint $table): void {
            $table->dropColumn(['billing_rank', 'stripe_product_id', 'stripe_price_id']);
        });
    }
};
