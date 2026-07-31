<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotations', 'invoices', 'recurring_invoices'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'tax_type')) {
                    $table->string('tax_type')->nullable()->after('total_amount');
                }

                if (! Schema::hasColumn($tableName, 'tax_rate')) {
                    $table->decimal('tax_rate', 8, 4)->default(0)->after('tax_type');
                }

                if (! Schema::hasColumn($tableName, 'tax_amount')) {
                    $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['quotations', 'invoices', 'recurring_invoices'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['tax_amount', 'tax_rate', 'tax_type'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
