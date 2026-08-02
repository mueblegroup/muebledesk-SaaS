<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'clients',
        'quotations',
        'quotation_items',
        'invoices',
        'invoice_items',
        'payments',
        'payment_receipts',
        'expenses',
        'recurring_invoices',
        'recurring_invoice_items',
        'e_invoices',
        'activity_logs',
        'api_keys',
        'settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $blueprint->index('company_id');
            });
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->backfillFromDirectUsers();
        $this->backfillFromParents();
        $this->backfillLegacySingleCompanyData();
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('company_id');
            });
        }
    }

    private function backfillFromDirectUsers(): void
    {
        $mappings = [
            ['clients', 'employee_id'],
            ['quotations', 'employee_id'],
            ['invoices', 'employee_id'],
            ['recurring_invoices', 'employee_id'],
            ['payments', 'recorded_by_employee_id'],
            ['expenses', 'recorded_by_user_id'],
            ['activity_logs', 'user_id'],
            ['api_keys', 'user_id'],
        ];

        foreach ($mappings as [$table, $userColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id') || ! Schema::hasColumn($table, $userColumn)) {
                continue;
            }

            DB::statement("UPDATE `{$table}` records INNER JOIN `users` users ON users.id = records.`{$userColumn}` SET records.company_id = users.current_company_id WHERE records.company_id IS NULL AND users.current_company_id IS NOT NULL");
        }
    }

    private function backfillFromParents(): void
    {
        $mappings = [
            ['quotation_items', 'quotation_id', 'quotations'],
            ['invoice_items', 'invoice_id', 'invoices'],
            ['payments', 'invoice_id', 'invoices'],
            ['payment_receipts', 'payment_id', 'payments'],
            ['recurring_invoice_items', 'recurring_invoice_id', 'recurring_invoices'],
            ['e_invoices', 'invoice_id', 'invoices'],
        ];

        foreach ($mappings as [$table, $foreignKey, $parent]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($parent) || ! Schema::hasColumn($table, 'company_id') || ! Schema::hasColumn($table, $foreignKey)) {
                continue;
            }

            DB::statement("UPDATE `{$table}` records INNER JOIN `{$parent}` parent_records ON parent_records.id = records.`{$foreignKey}` SET records.company_id = parent_records.company_id WHERE records.company_id IS NULL AND parent_records.company_id IS NOT NULL");
        }
    }

    private function backfillLegacySingleCompanyData(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $companyIds = DB::table('companies')->orderBy('id')->limit(2)->pluck('id');

        if ($companyIds->count() !== 1) {
            return;
        }

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                DB::table($table)->whereNull('company_id')->update(['company_id' => $companyIds->first()]);
            }
        }
    }
};
