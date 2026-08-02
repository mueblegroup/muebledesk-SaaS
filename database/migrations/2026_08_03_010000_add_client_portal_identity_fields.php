<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('country_code', 2)->nullable()->after('address');
            $table->string('preferred_timezone', 100)->default('Asia/Kuala_Lumpur')->after('country_code');
            $table->timestamp('profile_completed_at')->nullable()->after('preferred_timezone');
            $table->timestamp('whatsapp_verified_at')->nullable()->after('profile_completed_at');
        });

        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_user_id');
            $table->string('provider_email')->nullable();
            $table->text('avatar_url')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['country_code', 'preferred_timezone', 'profile_completed_at', 'whatsapp_verified_at']);
        });
    }
};
