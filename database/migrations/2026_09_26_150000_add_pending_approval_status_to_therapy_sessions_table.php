<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-09-26 (third round of client testing feedback): a client booking a therapist
 * directly, and a therapist scheduling a follow-up while closing a session, both used
 * to jump straight to 'scheduled' with zero admin/routing visibility. Both now land in
 * a new 'pending_approval' status first -- routing has to explicitly approve (or
 * decline) before the session is confirmed to the client. See
 * App\Http\Controllers\Admin\SessionRoutingController::approve()/decline().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('routed_at');
        });

        // Raw ALTER to widen the enum -- avoids requiring doctrine/dbal just to add one
        // value via the schema builder's ->change().
        DB::statement("ALTER TABLE therapy_sessions MODIFY status ENUM('pending_payment', 'routing', 'pending_approval', 'scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_payment'");
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });

        // Best-effort revert. Will fail if any row already has 'pending_approval' --
        // acceptable for a down() migration during active development.
        DB::statement("ALTER TABLE therapy_sessions MODIFY status ENUM('pending_payment', 'routing', 'scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_payment'");
    }
};
