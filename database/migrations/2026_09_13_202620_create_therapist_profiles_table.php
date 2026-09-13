<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('license_number');
            $table->string('license_state');
            $table->json('specialties')->nullable();
            $table->boolean('credentials_verified')->default(false);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('flw_subaccount_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_profiles');
    }
};