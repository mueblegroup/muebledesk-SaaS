<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            // Add columns for discount and sub_total
            $table->decimal('sub_total', 10, 2)->nullable()->default(0.00)->after('frequency'); // Or adjust position
            $table->string('discount_type')->nullable()->after('sub_total'); // 'percentage' or 'fixed'
            $table->decimal('discount_value', 10, 2)->nullable()->default(0.00)->after('discount_type'); // The % or fixed amount
            $table->decimal('discount_amount', 10, 2)->nullable()->default(0.00)->after('discount_value'); // The calculated discount amount
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            // Drop columns if rolling back the migration
            $table->dropColumn(['sub_total', 'discount_type', 'discount_value', 'discount_amount']);
        });
    }
};