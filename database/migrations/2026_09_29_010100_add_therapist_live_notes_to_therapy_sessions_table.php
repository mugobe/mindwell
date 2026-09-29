<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Therapist live notes, added 2026-09-29: the private notepad beside the video call on
 * the therapist's side, autosaved while they type. When they close the session, the
 * report's "Session summary" starts pre-filled with these notes (see
 * therapist/sessions/close.blade.php), so they don't write it from memory. Never shown
 * to the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->text('therapist_live_notes')->nullable()->after('plan_notes');
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn('therapist_live_notes');
        });
    }
};
