<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50);
            $table->string('event_id');
            $table->string('event_type')->nullable();
            $table->string('status', 30)->default('processing');
            $table->string('payload_hash', 64);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_webhook_events');
    }
};
