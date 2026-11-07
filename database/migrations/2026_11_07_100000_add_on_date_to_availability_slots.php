<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-off availability (2026-11-07): a block that is NOT weekly happens on one date.
 * Before this, "Repeats weekly" could not be unticked and one-off blocks were ignored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('availability_slots', 'on_date')) {
            Schema::table('availability_slots', function (Blueprint $table) {
                $table->date('on_date')->nullable()->after('day_of_week');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('availability_slots', 'on_date')) {
            Schema::table('availability_slots', function (Blueprint $table) {
                $table->dropColumn('on_date');
            });
        }
    }
};
