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
            DB::statement("ALTER TABLE quotations MODIFY status ENUM('draft', 'sent', 'approved', 'rejected', 'converted_to_invoice') NOT NULL DEFAULT 'draft'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            DB::statement("ALTER TABLE quotations MODIFY status ENUM('draft', 'sent', 'approved', 'rejected') NOT NULL DEFAULT 'draft'");
        });
    }
};
