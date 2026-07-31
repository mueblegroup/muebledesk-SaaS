<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (Schema::hasColumn('payments', 'method') && ! Schema::hasColumn('payments', 'payment_method')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('method', 'payment_method');
            });
        }

        if (! Schema::hasColumn('payments', 'payment_method')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_method')->nullable()->after('amount');
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'transaction_reference')) {
                $table->string('transaction_reference')->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('payments', 'notes')) {
                $table->text('notes')->nullable()->after('transaction_reference');
            }

            if (! Schema::hasColumn('payments', 'recorded_by_employee_id')) {
                $table->foreignId('recorded_by_employee_id')
                    ->nullable()
                    ->after('notes')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'recorded_by_employee_id')) {
                $table->dropConstrainedForeignId('recorded_by_employee_id');
            }

            if (Schema::hasColumn('payments', 'notes')) {
                $table->dropColumn('notes');
            }

            if (Schema::hasColumn('payments', 'transaction_reference')) {
                $table->dropColumn('transaction_reference');
            }
        });

        if (Schema::hasColumn('payments', 'payment_method') && ! Schema::hasColumn('payments', 'method')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('payment_method', 'method');
            });
        }
    }
};
