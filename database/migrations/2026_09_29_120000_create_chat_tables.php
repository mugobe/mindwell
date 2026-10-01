<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client ↔ therapist messaging, added 2026-09-29 (decided via AskUserQuestion:
 * auto-refresh rather than Laravel Reverb for now; only between a client and a
 * therapist who have had — or have booked — a session together).
 *
 * Deliberately named chat_* rather than conversations/messages: the original
 * mid-September database design already turned out to contain an empty
 * journal_entries table the assistant's sandbox didn't know about, and messaging was
 * in that same original plan, so a plain `messages` table may well exist too.
 *
 * Read state is kept per side on the conversation (client_last_read_at /
 * therapist_last_read_at) rather than per message — enough for unread badges and a
 * "Seen" marker, and far fewer writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->foreignUuid('therapist_profile_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('client_last_read_at')->nullable();
            $table->timestamp('therapist_last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['client_profile_id', 'therapist_profile_id']);
            $table->index(['therapist_profile_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 20); // 'client' | 'therapist'
            $table->text('body');
            $table->timestamps();

            $table->index(['chat_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
