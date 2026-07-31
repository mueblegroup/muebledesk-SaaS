<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE recurring_invoices MODIFY frequency ENUM('daily','weekly','monthly','quarterly','yearly','custom') NOT NULL");
    }

    public function down(): void
    {
        DB::table('recurring_invoices')
            ->where('frequency', 'custom')
            ->update([
                'frequency' => 'monthly',
                'repeat_every' => null,
                'repeat_unit' => null,
            ]);

        DB::statement("ALTER TABLE recurring_invoices MODIFY frequency ENUM('daily','weekly','monthly','quarterly','yearly') NOT NULL");
    }
};
