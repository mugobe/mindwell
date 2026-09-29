<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            // The structured closure step the client asked for: a therapist picks a
            // fixed outcome/recommendation and writes a short report. Setting these is
            // what actually moves a session from 'scheduled' to 'completed' — see
            // Therapist\SessionController::store().
            $table->enum('closure_outcome', [
                'schedule_next_session',
                'refer_to_specialist',
                'case_closed',
                'continue_monitoring',
                'other',
            ])->nullable()->after('routed_at');
            $table->text('closure_report')->nullable()->after('closure_outcome');
            $table->timestamp('closed_at')->nullable()->after('closure_report');
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn(['closure_outcome', 'closure_report', 'closed_at']);
        });
    }
};