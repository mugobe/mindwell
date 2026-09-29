<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Employer seat redemption (2026-09-27): a client covered by an active employer seat
 * skips payment the same way a subscription credit does, but nothing is metered or
 * decremented — see App\Models\EmployerSeat::activeFor(). This just widens the
 * existing payment_method enum so that path can be recorded distinctly from 'credit'
 * (subscription) and 'one_off' (placeholder payment).
 *
 * Raw ALTER, same pattern as the other enum-widening migrations in this table
 * (2026_09_26_130001_..., 2026_09_26_150000_...) — avoids requiring doctrine/dbal
 * just to widen one enum column via the schema builder's ->change().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE therapy_sessions MODIFY payment_method ENUM('credit', 'one_off', 'employer_seat') NULL");
    }

    public function down(): void
    {
        // Best-effort revert. Will fail if any row already has 'employer_seat' --
        // acceptable for a down() migration during active development.
        DB::statement("ALTER TABLE therapy_sessions MODIFY payment_method ENUM('credit', 'one_off') NULL");
    }
};
