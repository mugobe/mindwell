<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            // 2026-09-26 (second round of client testing feedback): the closure report
            // moves from one free-text box to five structured fields. The old
            // `closure_report` column is left in place (nullable) so sessions closed
            // before this change still display their original text — see the fallback
            // in admin/closed-sessions/show.blade.php.
            $table->text('presenting_concerns')->nullable()->after('closure_report');
            $table->text('session_summary')->nullable()->after('presenting_concerns');
            $table->text('progress_notes')->nullable()->after('session_summary');
            $table->text('risk_assessment')->nullable()->after('progress_notes');
            $table->text('plan_notes')->nullable()->after('risk_assessment');

            // A short note the therapist can attach when scheduling a follow-up from the
            // new calendar picker (e.g. "focus on sleep routine next time"). Stored on
            // the NEW follow-up session being created, deliberately separate from the
            // closure_report/structured fields above, which describe the session being
            // closed, not the one being booked.
            $table->text('follow_up_notes')->nullable()->after('plan_notes');
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'presenting_concerns',
                'session_summary',
                'progress_notes',
                'risk_assessment',
                'plan_notes',
                'follow_up_notes',
            ]);
        });
    }
};