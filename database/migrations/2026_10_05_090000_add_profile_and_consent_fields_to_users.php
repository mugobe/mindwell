<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 (2026-10-05), from Dr. Ntabade's CLIENT DRAFT:
 *  - first_name / last_name / date_of_birth / gender at sign-up. `name` stays (it's
 *    used everywhere) and is kept as "first last".
 *  - consent records: when the person accepted the Terms of Use, the Privacy Policy and
 *    the "not a replacement for emergency care" statement, and which version of the
 *    documents they accepted (DPPA 2019 — consent should be recorded, not assumed).
 *
 * Put on `users` (not client_profiles) so they work for any role and because the
 * ClientProfile model isn't in the assistant's copy of the code. Guarded with hasColumn.
 */
return new class extends Migration
{
    private array $columns = [
        'first_name', 'last_name', 'date_of_birth', 'gender',
        'terms_accepted_at', 'privacy_accepted_at', 'emergency_disclaimer_accepted_at', 'legal_version',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->nullable();
            }
            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 100)->nullable();
            }
            if (! Schema::hasColumn('users', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable();
            }
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 30)->nullable();
            }
            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'privacy_accepted_at')) {
                $table->timestamp('privacy_accepted_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'emergency_disclaimer_accepted_at')) {
                $table->timestamp('emergency_disclaimer_accepted_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'legal_version')) {
                $table->string('legal_version', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
