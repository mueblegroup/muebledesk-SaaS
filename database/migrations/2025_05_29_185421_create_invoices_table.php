<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->date('date');
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'overdue', 'partially_paid'])->default('pending'); // Added partially_paid
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->decimal('amount_paid', 10, 2)->default(0.00); // Track how much has been paid
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->onDelete('set null'); // Can be generated from a quote
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
