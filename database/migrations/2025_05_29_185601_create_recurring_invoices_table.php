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
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            // Option 1: Copy items from a template invoice (simpler)
            // $table->foreignId('template_invoice_id')->nullable()->constrained('invoices')->onDelete('set null');

            // Option 2: Store items directly (more flexible if template invoice is deleted)
            $table->string('invoice_prefix')->nullable(); // e.g., INV-
            $table->string('item_name'); // For single item or generic
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total_amount', 10, 2);

            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable(); // If there's an end to the recurring payments
            $table->date('next_invoice_date'); // When the next invoice should be generated
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true); // To easily enable/disable recurring invoices
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
