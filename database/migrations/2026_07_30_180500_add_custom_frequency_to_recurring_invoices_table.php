<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->unsignedInteger('repeat_every')->nullable()->after('frequency');
            $table->string('repeat_unit', 20)->nullable()->after('repeat_every');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropColumn(['repeat_every', 'repeat_unit']);
        });
    }
};
