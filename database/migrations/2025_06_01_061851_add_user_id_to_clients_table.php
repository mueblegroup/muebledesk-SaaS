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
        Schema::table('clients', function (Blueprint $table) {
            // Add the user_id column
            // It should be nullable because not all clients will have a linked customer user account initially
            $table->foreignId('user_id')->nullable()->after('employee_id')->constrained('users')->onDelete('set null');
            // Consider adding a unique constraint if one user can only be linked to one client
            // $table->unique('user_id'); // Add this if you want a 1:1 relationship between user and client
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['user_id']);
            // Then drop the column
            $table->dropColumn('user_id');
        });
    }
};