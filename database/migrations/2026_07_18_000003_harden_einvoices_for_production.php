<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('einvoices', function (Blueprint $table) {
            $table->string('correlation_id')->nullable()->after('failure_reason');
            $table->unsignedInteger('submission_attempts')->default(0)->after('correlation_id');
            $table->timestamp('retry_after_at')->nullable()->after('submission_attempts');
            $table->timestamp('notified_at')->nullable()->after('retry_after_at');
            $table->string('cancellation_reason', 300)->nullable()->after('cancelled_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('einvoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['correlation_id', 'submission_attempts', 'retry_after_at', 'notified_at', 'cancellation_reason']);
        });
    }
};
