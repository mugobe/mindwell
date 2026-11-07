<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Care features (2026-11-07, roadmap 36, 37, 39, 69, 77):
 *  - resources: the library the admin builds (articles, PDFs, audio, video and links);
 *  - resource_shares: a therapist sends a resource to a client;
 *  - therapy_goals + therapy_goal_updates: goals a therapist sets, the client tracks;
 *  - homework: tasks a therapist gives, the client answers with words or a file.
 * Files are on the private "local" disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resources')) {
            Schema::create('resources', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title', 160);
                $table->string('summary', 300)->nullable();
                $table->string('kind', 12);          // article | pdf | audio | video | link
                $table->string('topic', 60)->nullable();
                $table->longText('body')->nullable();  // article text
                $table->string('file_path')->nullable();
                $table->string('file_name', 160)->nullable();
                $table->string('file_mime', 100)->nullable();
                $table->unsignedInteger('file_size')->nullable();
                $table->string('url', 500)->nullable(); // video or web link
                $table->unsignedSmallInteger('minutes')->nullable();
                $table->boolean('is_published')->default(true);
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['is_published', 'topic']);
            });
        }

        if (! Schema::hasTable('resource_shares')) {
            Schema::create('resource_shares', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('resource_id')->constrained('resources')->cascadeOnDelete();
                $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
                $table->foreignUuid('therapist_profile_id')->nullable()->constrained('therapist_profiles')->nullOnDelete();
                $table->string('note', 500)->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamps();
                $table->index(['client_profile_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('therapy_goals')) {
            Schema::create('therapy_goals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
                $table->foreignUuid('therapist_profile_id')->nullable()->constrained('therapist_profiles')->nullOnDelete();
                $table->string('title', 200);
                $table->text('details')->nullable();
                $table->string('status', 12)->default('active'); // active | achieved | stopped
                $table->unsignedTinyInteger('progress')->default(0); // 0–100
                $table->date('target_on')->nullable();
                $table->timestamp('achieved_at')->nullable();
                $table->timestamps();
                $table->index(['client_profile_id', 'status']);
            });
        }

        if (! Schema::hasTable('therapy_goal_updates')) {
            Schema::create('therapy_goal_updates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('therapy_goal_id')->constrained('therapy_goals')->cascadeOnDelete();
                $table->string('by_role', 12);        // client | therapist
                $table->unsignedTinyInteger('progress')->nullable();
                $table->string('note', 1000)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homework')) {
            Schema::create('homework', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
                $table->foreignUuid('therapist_profile_id')->nullable()->constrained('therapist_profiles')->nullOnDelete();
                $table->foreignUuid('resource_id')->nullable()->constrained('resources')->nullOnDelete();
                $table->string('title', 200);
                $table->text('instructions')->nullable();
                $table->date('due_on')->nullable();
                $table->string('status', 12)->default('assigned'); // assigned | done
                $table->text('reply')->nullable();
                $table->string('reply_file_path')->nullable();
                $table->string('reply_file_name', 160)->nullable();
                $table->string('reply_file_mime', 100)->nullable();
                $table->unsignedInteger('reply_file_size')->nullable();
                $table->timestamp('done_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['client_profile_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homework');
        Schema::dropIfExists('therapy_goal_updates');
        Schema::dropIfExists('therapy_goals');
        Schema::dropIfExists('resource_shares');
        Schema::dropIfExists('resources');
    }
};
