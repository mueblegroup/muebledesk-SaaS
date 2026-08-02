<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs') || ! Schema::hasColumn('activity_logs', 'company_id')
            || ! Schema::hasColumn('activity_logs', 'actor_id') || ! Schema::hasTable('users')) {
            return;
        }

        DB::statement('UPDATE activity_logs logs INNER JOIN users actors ON actors.id = logs.actor_id SET logs.company_id = actors.current_company_id WHERE logs.company_id IS NULL AND actors.current_company_id IS NOT NULL');

        if (Schema::hasTable('companies')) {
            $companyIds = DB::table('companies')->orderBy('id')->limit(2)->pluck('id');
            if ($companyIds->count() === 1) {
                DB::table('activity_logs')->whereNull('company_id')->update(['company_id' => $companyIds->first()]);
            }
        }
    }

    public function down(): void
    {
        // Data ownership backfills are intentionally not reversed.
    }
};
