<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Therapist onboarding (2026-11-03): the multi-step application that replaces the single
 * register/therapist form, its private documents, and recorded consents.
 *
 *  - therapist_applications: one per applicant. Answers for every step live in `answers`
 *    (JSON), so a question can be added in config/onboarding.php without a migration.
 *  - therapist_application_documents: uploaded files, stored on the PRIVATE disk
 *    (storage/app/private), only ever streamed to admins.
 *  - consent_records: who agreed to which document, which version, and when. General on
 *    purpose, so other consents can use it later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('therapist_profile_id')->nullable()->constrained('therapist_profiles')->nullOnDelete();
            // draft → submitted → (changes_requested → submitted …) → approved | declined
            $table->string('status', 20)->default('draft')->index();
            $table->json('answers')->nullable();
            $table->json('checklist')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedSmallInteger('submissions')->default(0);
            $table->timestamps();
        });

        Schema::create('therapist_application_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('therapist_application_id');
            $table->foreign('therapist_application_id', 'tad_application_fk')->references('id')->on('therapist_applications')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
            $table->unique(['therapist_application_id', 'kind'], 'tad_application_kind_unique');
        });

        Schema::create('consent_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('document', 50);
            $table->string('version', 20);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'document', 'version'], 'consent_records_user_doc_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('therapist_application_documents');
        Schema::dropIfExists('therapist_applications');
    }
};
