<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client journal, added 2026-09-29 (decided via AskUserQuestion alongside the video
 * room redesign). One table holds both kinds of entry a client writes:
 *
 *  - kind 'session': the notepad beside the video call. Exactly one per client per
 *    session (unique index below), autosaved while they type.
 *  - kind 'daily':   the "My journal" page — a mood rating plus a few words, any day,
 *    session or not. As many as they like.
 *
 * Privacy (decided the same day: "Private, with optional sharing"): every entry is
 * private to the client unless THEY flip shared_with_therapist on for that entry.
 * The therapist's own in-session notes are NOT here — they live on
 * therapy_sessions.therapist_live_notes (see the next migration), because they belong
 * to the clinical record, not the client's personal journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The first `migrate` on the user's machine (2026-09-29) failed with "Table
        // 'journal_entries' already exists". An empty journal_entries table was already
        // in the database — most likely from the original database design (a journal
        // was planned from the start), possibly from an interrupted earlier run. An EMPTY
        // one is replaced with the structure this feature needs; one holding data is
        // never dropped automatically.
        if (Schema::hasTable('journal_entries')) {
            $rows = DB::table('journal_entries')->count();

            if ($rows > 0) {
                throw new RuntimeException(
                    "A journal_entries table already exists and contains {$rows} row(s), so it was NOT replaced. "
                    .'Back up or remove that data first, then run `php artisan migrate` again.'
                );
            }

            Schema::drop('journal_entries');
        }

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            // Set only for kind 'session'. nullOnDelete: a session being removed
            // shouldn't delete the client's own words.
            $table->foreignUuid('therapy_session_id')->nullable()->constrained('therapy_sessions')->nullOnDelete();
            $table->string('kind', 20); // 'session' | 'daily'
            $table->unsignedTinyInteger('mood')->nullable(); // 1-5, daily entries only
            $table->text('body')->nullable();
            $table->boolean('shared_with_therapist')->default(false);
            $table->timestamps();

            $table->index(['client_profile_id', 'kind', 'created_at']);
            // One session notepad per client per session. Daily entries have a NULL
            // therapy_session_id, and MySQL allows any number of NULLs in a unique index.
            $table->unique(['client_profile_id', 'therapy_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};