<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('einvoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('einvoice_submission_id')->nullable()->constrained('einvoice_submissions')->nullOnDelete();
            $table->string('environment', 20)->default('sandbox');
            $table->string('document_type', 20)->default('invoice');
            $table->string('document_version', 10)->default('1.0');
            $table->string('status', 40)->default('draft')->index();
            $table->string('internal_document_number', 50);
            $table->uuid('myinvois_uuid')->nullable()->unique();
            $table->string('long_id')->nullable();
            $table->string('document_hash', 64)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('validation_errors')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['invoice_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einvoices');
    }
};
