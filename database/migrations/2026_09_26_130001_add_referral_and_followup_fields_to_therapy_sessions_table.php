<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives therapist closure real follow-through (2026-09-26 client request):
 *  - `previous_session_id` links a "schedule another session" follow-up back to the
 *    session it was recommended from.
 *  - `referral_facility_id` / `referred_at` / `referral_assigned_at` support the
 *    "refer out" -> routing-team-assigns-a-facility flow (see the new `facilities`
 *    table and App\Http\Controllers\Admin\ReferralController).
 *  - `routing_method` gains a `therapist_scheduled` value for a follow-up the
 *    therapist scheduled directly while closing the previous session, distinct from
 *    a client self-booking one themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->foreignUuid('previous_session_id')->nullable()->after('intake_assessment_id')
                ->constrained('therapy_sessions')->nullOnDelete();
            $table->foreignUuid('referral_facility_id')->nullable()->after('closed_at')
                ->constrained('facilities')->nullOnDelete();
            $table->timestamp('referred_at')->nullable()->after('referral_facility_id');
            $table->timestamp('referral_assigned_at')->nullable()->after('referred_at');
        });

        // Raw ALTER to add the new enum value -- avoids requiring doctrine/dbal just to
        // widen one enum column via the schema builder's ->change().
        DB::statement("ALTER TABLE therapy_sessions MODIFY routing_method ENUM('algorithmic', 'manual', 'self_booked', 'therapist_scheduled') NULL");
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropForeign(['previous_session_id']);
            $table->dropForeign(['referral_facility_id']);
            $table->dropColumn(['previous_session_id', 'referral_facility_id', 'referred_at', 'referral_assigned_at']);
        });

        // Best-effort revert. Will fail if any row already has 'therapist_scheduled' --
        // that's acceptable for a down() migration during active development.
        DB::statement("ALTER TABLE therapy_sessions MODIFY routing_method ENUM('algorithmic', 'manual', 'self_booked') NULL");
    }
};
