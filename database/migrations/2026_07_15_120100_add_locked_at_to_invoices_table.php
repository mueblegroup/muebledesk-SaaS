<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (! Schema::hasColumn('invoices', 'locked_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->timestamp('locked_at')->nullable()->after('amount_paid')->index();
            });
        }

        DB::table('invoices')
            ->where('amount_paid', '>', 0)
            ->whereNull('locked_at')
            ->update(['locked_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'locked_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('locked_at');
            });
        }
    }
};
