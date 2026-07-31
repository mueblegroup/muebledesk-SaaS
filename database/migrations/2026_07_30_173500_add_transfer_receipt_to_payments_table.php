<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transfer_receipt_path')->nullable()->after('transaction_id');
            $table->string('transfer_receipt_original_name')->nullable()->after('transfer_receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['transfer_receipt_path', 'transfer_receipt_original_name']);
        });
    }
};
