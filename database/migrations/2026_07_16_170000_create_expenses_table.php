<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            return;
        }

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('expense_number')->unique();
            $table->date('expense_date')->index();
            $table->string('category')->index();
            $table->string('vendor')->nullable()->index();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('MYR');
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable()->index();
            $table->boolean('is_billable')->default(false)->index();
            $table->boolean('is_tax_deductible')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['expense_date', 'category']);
            $table->index(['recorded_by_user_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
