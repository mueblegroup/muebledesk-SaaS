<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('einvoices') || Schema::hasColumn('einvoices', 'company_id')) {
            return;
        }

        Schema::table('einvoices', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->index('company_id');
        });

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'company_id')) {
            DB::statement(
                'UPDATE `einvoices` records '
                .'INNER JOIN `invoices` parent_records ON parent_records.id = records.invoice_id '
                .'SET records.company_id = parent_records.company_id '
                .'WHERE records.company_id IS NULL AND parent_records.company_id IS NOT NULL'
            );
        }

        if (Schema::hasTable('companies')) {
            $companyIds = DB::table('companies')->orderBy('id')->limit(2)->pluck('id');

            if ($companyIds->count() === 1) {
                DB::table('einvoices')
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyIds->first()]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('einvoices') || ! Schema::hasColumn('einvoices', 'company_id')) {
            return;
        }

        Schema::table('einvoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
