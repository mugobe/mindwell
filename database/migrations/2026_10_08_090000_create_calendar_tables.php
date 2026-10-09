<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar (2026-10-08, roadmap 75 — Dr. Ntabade's THERAPIST DRAFT item 7). Decided with
 * Collins: clients and therapists ASK to reschedule/cancel, admin approves; therapists
 * ask for time off, admin approves; admin can also change things directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_change_requests')) {
            Schema::create('schedule_change_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('therapy_session_id')->constrained('therapy_sessions')->cascadeOnDelete();
                $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requester_role', 20);              // client | therapist | admin
                $table->string('type', 20);                         // reschedule | cancel
                $table->timestamp('current_at')->nullable();        // the time when asked
                $table->timestamp('proposed_at')->nullable();       // reschedule only
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('pending');   // pending | approved | declined | withdrawn
                $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->json('outcome')->nullable();                // what approval did (credit back, refund…)
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('therapist_time_off')) {
            Schema::create('therapist_time_off', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('therapist_profile_id')->constrained('therapist_profiles')->cascadeOnDelete();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('reason')->nullable();               // seen by admin only
                $table->string('status', 20)->default('pending');   // pending | approved | declined | cancelled
                $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamps();
                $table->index(['therapist_profile_id', 'status', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_time_off');
        Schema::dropIfExists('schedule_change_requests');
    }
};
