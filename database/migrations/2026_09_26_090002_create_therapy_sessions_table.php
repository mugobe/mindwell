<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapy_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->foreignUuid('therapist_profile_id')->nullable()->constrained('therapist_profiles')->nullOnDelete();
            // Only set when this session originated from a fresh intake (the lobby/routing path).
            // Null for a directly self-booked rebooking with a therapist the client already has.
            $table->foreignUuid('intake_assessment_id')->nullable()->constrained('intake_assessments')->nullOnDelete();

            $table->enum('status', ['pending_payment', 'routing', 'scheduled', 'completed', 'cancelled'])->default('pending_payment');
            $table->enum('payment_method', ['credit', 'one_off'])->nullable();
            $table->decimal('amount_charged', 10, 2)->nullable();
            $table->enum('routing_method', ['algorithmic', 'manual', 'self_booked'])->nullable();

            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(70);

            $table->timestamp('entered_lobby_at')->nullable();
            $table->timestamp('routed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapy_sessions');
    }
};
