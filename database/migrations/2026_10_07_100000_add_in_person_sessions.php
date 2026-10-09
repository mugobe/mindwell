<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-person sessions (2026-10-07, roadmap 68, decided with Collins):
 *  - venues: the places Mindwell sees clients face to face, managed by admin
 *  - therapist_profiles.offers_in_person: switched on per therapist by admin
 *  - therapy_sessions.session_mode ('video' | 'in_person'), plus where it happens:
 *    a venue from the list and/or free-text location details set by admin on approval
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venues')) {
            Schema::create('venues', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('address')->nullable();
                $table->text('directions')->nullable();
                $table->string('map_url', 500)->nullable();
                $table->string('phone', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('therapist_profiles') && ! Schema::hasColumn('therapist_profiles', 'offers_in_person')) {
            Schema::table('therapist_profiles', function (Blueprint $table) {
                $table->boolean('offers_in_person')->default(false);
            });
        }

        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('therapy_sessions', 'session_mode')) {
                $table->string('session_mode', 20)->default('video');
            }
            if (! Schema::hasColumn('therapy_sessions', 'venue_id')) {
                $table->foreignUuid('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            }
            if (! Schema::hasColumn('therapy_sessions', 'location_details')) {
                $table->text('location_details')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('therapy_sessions', 'venue_id')) {
                $table->dropConstrainedForeignId('venue_id');
            }
            foreach (['session_mode', 'location_details'] as $col) {
                if (Schema::hasColumn('therapy_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('therapist_profiles', 'offers_in_person')) {
            Schema::table('therapist_profiles', fn (Blueprint $table) => $table->dropColumn('offers_in_person'));
        }

        Schema::dropIfExists('venues');
    }
};
