<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60)->default('general')->index();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30)->default('stripe');
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_invoice_id')->nullable()->unique();
            $table->string('provider_customer_id')->nullable()->index();
            $table->string('status', 30)->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->string('description')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('platform_settings');
    }
};
