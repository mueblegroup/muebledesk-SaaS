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
        Schema::table('quotations', function (Blueprint $table) {
            // Add sub_total (total before discount)
            $table->decimal('sub_total', 10, 2)->after('status')->default(0.00);

            // Add discount fields
            $table->string('discount_type')->after('sub_total')->nullable(); // 'percentage' or 'fixed'
            $table->decimal('discount_value', 10, 2)->after('discount_type')->nullable()->default(0.00);

            // You might want to update total_amount's default if it was not 0.00
            // $table->decimal('total_amount', 10, 2)->default(0.00)->change(); // Example if you need to change existing
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('sub_total');
            $table->dropColumn('discount_type');
            $table->dropColumn('discount_value');
        });
    }
};