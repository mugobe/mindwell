<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Therapist workspace (2026-10-06, roadmap 71, 72, 73, 78, 67, 79) — from Dr. Ntabade's
 * THERAPIST DRAFT. Every column guarded with hasColumn.
 *
 * users (client details for the client file — set at sign-up or in Settings → My details)
 *   client_number               — "Client 0001" IDs, given out in order (App\Models\User::ensureClientNumber)
 *   preferred_language
 *   emergency_contact_name / _phone / _relationship
 *
 * therapy_sessions (richer session notes, filled when the therapist closes a session)
 *   session_number              — this client's nth completed session, counted automatically
 *   interventions_used, homework, goals_progress
 *
 * therapist_profiles (therapist edits their own profile)
 *   professional_title, qualifications, languages, bio, photo_path
 *   pending_license_number / pending_license_state / license_change_requested_at —
 *     a licence change waits for admin approval (decided with Collins: only licence
 *     details need approval); the current licence stays in force meanwhile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'client_number')) {
                $table->unsignedInteger('client_number')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'preferred_language')) {
                $table->string('preferred_language', 60)->nullable();
            }
            if (! Schema::hasColumn('users', 'emergency_contact_name')) {
                $table->string('emergency_contact_name', 120)->nullable();
            }
            if (! Schema::hasColumn('users', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'emergency_contact_relationship')) {
                $table->string('emergency_contact_relationship', 60)->nullable();
            }
        });

        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('therapy_sessions', 'session_number')) {
                $table->unsignedInteger('session_number')->nullable();
            }
            foreach (['interventions_used', 'homework', 'goals_progress'] as $column) {
                if (! Schema::hasColumn('therapy_sessions', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });

        Schema::table('therapist_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('therapist_profiles', 'professional_title')) {
                $table->string('professional_title', 120)->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'qualifications')) {
                $table->text('qualifications')->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'languages')) {
                $table->string('languages', 255)->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'bio')) {
                $table->text('bio')->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'photo_path')) {
                $table->string('photo_path')->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'pending_license_number')) {
                $table->string('pending_license_number')->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'pending_license_state')) {
                $table->string('pending_license_state')->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'license_change_requested_at')) {
                $table->timestamp('license_change_requested_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $drop = [
            'users' => ['client_number', 'preferred_language', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship'],
            'therapy_sessions' => ['session_number', 'interventions_used', 'homework', 'goals_progress'],
            'therapist_profiles' => ['professional_title', 'qualifications', 'languages', 'bio', 'photo_path', 'pending_license_number', 'pending_license_state', 'license_change_requested_at'],
        ];

        foreach ($drop as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        if ($column === 'client_number') {
                            $table->dropUnique(['client_number']);
                        }
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
