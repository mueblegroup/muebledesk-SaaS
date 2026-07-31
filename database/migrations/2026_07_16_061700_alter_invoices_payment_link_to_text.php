<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'payment_link')) {
            return;
        }

        DB::statement('ALTER TABLE `invoices` MODIFY `payment_link` TEXT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'payment_link')) {
            return;
        }

        DB::statement('ALTER TABLE `invoices` MODIFY `payment_link` VARCHAR(255) NULL');
    }
};
