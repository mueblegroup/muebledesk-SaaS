<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('hitpay_payment_id')->nullable()->after('payment_link'); // Or wherever you want it
            $table->string('payment_method')->nullable()->after('hitpay_payment_id'); // If you want to store it
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('hitpay_payment_id');
            $table->dropColumn('payment_method');
        });
    }
};