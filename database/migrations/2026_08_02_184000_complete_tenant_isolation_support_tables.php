<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['einvoice_submissions', 'webhook_events'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->foreignId('company_id')->nullable()->after('id')
                        ->constrained('companies')->cascadeOnDelete();
                    $blueprint->index('company_id');
                });
            }
        }

        if (Schema::hasTable('einvoice_submissions') && Schema::hasTable('einvoices')
            && Schema::hasColumn('einvoice_submissions', 'company_id') && Schema::hasColumn('einvoices', 'company_id')) {
            DB::statement('UPDATE einvoice_submissions submissions INNER JOIN einvoices documents ON documents.einvoice_submission_id = submissions.id SET submissions.company_id = documents.company_id WHERE submissions.company_id IS NULL AND documents.company_id IS NOT NULL');
        }

        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'company_id')) {
            $this->dropIndexIfExists('settings', 'settings_key_unique');
            $this->addUniqueIfMissing('settings', ['company_id', 'key'], 'settings_company_key_unique');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            $this->dropIndexIfExists('settings', 'settings_company_key_unique');
        }

        foreach (['webhook_events', 'einvoice_submissions'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('company_id'));
            }
        }
    }

    private function addUniqueIfMissing(string $table, array $columns, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
        }
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($name));
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))->contains(fn ($index) => ($index->Key_name ?? null) === $name);
    }
};
