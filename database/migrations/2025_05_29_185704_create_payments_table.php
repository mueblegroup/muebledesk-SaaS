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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('payment_method')->comment('e.g., hitpay, bank_transfer, cash');
            $table->string('transaction_reference')->nullable(); // Bank ref, transaction ID
            $table->string('transaction_id')->nullable()->unique(); // For HitPay or other gateways
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_employee_id')->nullable()->constrained('users')->onDelete('set null'); // Who recorded it
            $table->boolean('is_deposit')->default(false); // For deposit payments
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
