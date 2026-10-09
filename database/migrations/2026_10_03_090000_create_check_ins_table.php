<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progress & mood insights (2026-10-03) — see App\Models\CheckIn.
 *
 * check_ins: the two-weekly PHQ-9 + GAD-7 check-in (same questions as intake, so the
 * scores line up with the intake baseline). responses keeps every answer for the
 * therapist; self_harm marks a positive PHQ-9 item 9, which also raises a CrisisFlag.
 *
 * users:
 *  - check_in_reminded_at: when we last sent the "check-in is ready" email/SMS.
 *  - share_mood_with_therapist: the client's choice to let their therapist see their
 *    daily mood CHART (numbers only — journal text stays private either way).
 *
 * Guarded (hasTable / hasColumn) like every other migration in this project.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('check_ins')) {
            Schema::create('check_ins', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
                $table->unsignedTinyInteger('phq9_score');
                $table->unsignedTinyInteger('gad7_score');
                $table->json('responses');
                $table->boolean('self_harm')->default(false);
                $table->foreignUuid('crisis_flag_id')->nullable()->constrained('crisis_flags')->nullOnDelete();
                $table->timestamps();

                $table->index(['client_profile_id', 'created_at']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'check_in_reminded_at')) {
                $table->timestamp('check_in_reminded_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'share_mood_with_therapist')) {
                $table->boolean('share_mood_with_therapist')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');

        Schema::table('users', function (Blueprint $table) {
            foreach (['check_in_reminded_at', 'share_mood_with_therapist'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
