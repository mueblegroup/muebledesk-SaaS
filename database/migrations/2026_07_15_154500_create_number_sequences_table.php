<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('number_sequences')) {
            return;
        }

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->string('period_key')->default('global');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['document_type', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
