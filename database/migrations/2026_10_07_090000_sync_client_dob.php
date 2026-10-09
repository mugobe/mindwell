<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * client_profiles.dob (original) and users.date_of_birth (Phase 0) hold the same thing.
 * users.date_of_birth is the one Mindwell reads. This fills whichever side is blank from
 * the other; User::booted() keeps them matching from now on. Never overwrites a value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('client_profiles', 'dob') || ! Schema::hasColumn('users', 'date_of_birth')) {
            return;
        }

        $rows = DB::table('client_profiles')
            ->join('users', 'users.id', '=', 'client_profiles.user_id')
            ->get(['client_profiles.id as profile_id', 'users.id as user_id', 'client_profiles.dob', 'users.date_of_birth']);

        foreach ($rows as $row) {
            if ($row->date_of_birth === null && $row->dob !== null) {
                DB::table('users')->where('id', $row->user_id)->update(['date_of_birth' => $row->dob]);
            } elseif ($row->dob === null && $row->date_of_birth !== null) {
                DB::table('client_profiles')->where('id', $row->profile_id)->update(['dob' => $row->date_of_birth]);
            }
        }
    }

    public function down(): void
    {
    }
};
