<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders & notifications (email + SMS via Pandora), added 2026-09-30.
 *
 *  - users.phone / notify_email / notify_sms — where and whether to reach someone.
 *    Every column is guarded with hasColumn(): the original September database design
 *    (never visible to the assistant) has already surprised us once with an existing
 *    table, and a users.phone column from that design is plausible.
 *  - therapy_sessions.*_sent_at — so each reminder/alert goes out at most once.
 *  - chat_conversations.*_notified_at — "you have a new message" goes out at most once
 *    per batch of unread messages, per side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'notify_email')) {
                $table->boolean('notify_email')->default(true);
            }
            if (! Schema::hasColumn('users', 'notify_sms')) {
                $table->boolean('notify_sms')->default(true);
            }
        });

        Schema::table('therapy_sessions', function (Blueprint $table) {
            foreach (['reminder_24h_sent_at', 'reminder_1h_sent_at', 'room_alert_sent_at'] as $column) {
                if (! Schema::hasColumn('therapy_sessions', $column)) {
                    $table->timestamp($column)->nullable();
                }
            }
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            foreach (['client_notified_at', 'therapist_notified_at'] as $column) {
                if (! Schema::hasColumn('chat_conversations', $column)) {
                    $table->timestamp($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['client_notified_at', 'therapist_notified_at']);
        });
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn(['reminder_24h_sent_at', 'reminder_1h_sent_at', 'room_alert_sent_at']);
        });
        // users.phone is deliberately left in place on rollback: it may pre-date this
        // migration (see the note above), and dropping it could lose real data.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'notify_sms']);
        });
    }
};
