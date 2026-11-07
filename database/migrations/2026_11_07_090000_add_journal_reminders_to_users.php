<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional journal reminders (2026-11-07): null = off, or morning | evening | both.
 * Off unless the client turns them on in Settings > Notifications. Sent by email only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'journal_reminders')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('journal_reminders', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'journal_reminders')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('journal_reminders');
            });
        }
    }
};
