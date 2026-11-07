<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Message attachments (2026-11-07): one photo or PDF per message, up to 10 MB, both ways.
 * Files live on the private "local" disk (storage/app/private/chat-attachments/...), and
 * only the two people in the conversation (and admins) can open them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chat_messages', 'attachment_path')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name', 160)->nullable();
                $table->string('attachment_mime', 100)->nullable();
                $table->unsignedInteger('attachment_size')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_messages', 'attachment_path')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
            });
        }
    }
};
