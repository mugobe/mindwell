<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session feedback & ratings + password reset (2026-10-04).
 *
 * session_ratings — one row per session (unique), written by the client after it.
 *   rating is null when they chose "Skip" (so they're not asked again). Named
 *   session_ratings because the original design's empty `reviews` stub table exists.
 *   Admins see everything; therapists only ever see their own average (min 3 ratings).
 *
 * therapy_sessions
 *   client_joined_at        — first time the client got a video token for this session
 *                             (so no-shows are never asked "how was your session?").
 *   rating_reminder_sent_at — the one "how was it?" email, the day after.
 *
 * users.must_change_password — set when an admin gives someone a temporary password;
 *   App\Http\Middleware\EnsurePasswordChanged sends them to "choose a new password"
 *   until they do.
 *
 * Guarded (hasTable / hasColumn) like every other migration in this project.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('session_ratings')) {
            Schema::create('session_ratings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('therapy_session_id')->unique()->constrained('therapy_sessions')->cascadeOnDelete();
                $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
                $table->foreignUuid('therapist_profile_id')->nullable()->constrained('therapist_profiles')->nullOnDelete();
                $table->unsignedTinyInteger('rating')->nullable(); // 1-5, null = skipped
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->index(['therapist_profile_id', 'created_at']);
            });
        }

        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('therapy_sessions', 'client_joined_at')) {
                $table->timestamp('client_joined_at')->nullable();
            }
            if (! Schema::hasColumn('therapy_sessions', 'rating_reminder_sent_at')) {
                $table->timestamp('rating_reminder_sent_at')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_ratings');

        Schema::table('therapy_sessions', function (Blueprint $table) {
            foreach (['client_joined_at', 'rating_reminder_sent_at'] as $column) {
                if (Schema::hasColumn('therapy_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
        });
    }
};
