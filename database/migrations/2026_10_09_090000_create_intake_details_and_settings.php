<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer intake (2026-10-09, roadmap 63 — Dr. Ntabade's CLIENT DRAFT item 3 and THERAPIST
 * DRAFT item 4). Decided with Collins:
 *  - an "About you" step before the PHQ-9/GAD-7: topics, mood now, background, goals,
 *    preferences;
 *  - admin switches each section on/off (Admin → Setup → Intake questions); everything
 *    that's on can be skipped;
 *  - shown to the therapist and admin only — NOT used for matching;
 *  - clients who did intake before this are asked once on their dashboard.
 *
 * app_settings is a small key/value store for settings admins change in the app (the
 * first user is the intake sections; more can live here later).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->string('key', 100)->primary();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_intake_details')) {
            Schema::create('client_intake_details', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('client_profile_id')->unique()->constrained('client_profiles')->cascadeOnDelete();
                $table->json('topics')->nullable();                 // labels picked from the topic list
                $table->text('own_words')->nullable();              // "in your own words"
                $table->unsignedTinyInteger('mood_now')->nullable(); // 1–5, same scale as the journal
                $table->string('counselling_before', 20)->nullable(); // never | past | now
                $table->string('medication', 20)->nullable();         // yes | no | prefer_not
                $table->text('background_notes')->nullable();
                $table->json('goals')->nullable();                  // labels picked from the goals list
                $table->text('goals_text')->nullable();
                $table->string('pref_therapist_gender', 20)->nullable(); // any | female | male
                $table->string('pref_language', 60)->nullable();
                $table->string('pref_mode', 20)->nullable();          // any | video | in_person
                $table->json('skipped')->nullable();                // section keys the client skipped
                $table->string('source', 20)->default('intake');    // intake | later (dashboard / My details)
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('dismissed_at')->nullable();       // existing client said "not now"
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_intake_details');
        Schema::dropIfExists('app_settings');
    }
};
