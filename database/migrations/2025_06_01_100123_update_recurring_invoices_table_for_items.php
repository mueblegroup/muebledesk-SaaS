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
        Schema::table('recurring_invoices', function (Blueprint $table) {
            // Drop columns related to single item
            $table->dropColumn(['item_name', 'description', 'price', 'quantity']);

            // 'total_amount' remains but will now be the sum of related items
            // You might want to make it nullable if you anticipate cases where it's 0 before items are added
            // $table->decimal('total_amount', 10, 2)->change(); // Use ->change() if total_amount already exists and you just want to modify it

            // If total_amount wasn't nullable before, and you want it to be:
            // $table->decimal('total_amount', 10, 2)->nullable()->change();

            // Add any other new fields for the recurring invoice itself if needed, e.g., notes
            // $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            // Re-add columns if you ever roll back this migration
            $table->string('item_name')->nullable(); // Make nullable for flexibility on rollback
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('quantity')->nullable();

            // Revert total_amount if you changed it (e.g., from nullable to not nullable)
            // $table->decimal('total_amount', 10, 2)->change(); // or add ->nullable(false)->change()
        });
    }
};