<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile API v1 (2026-10-09). Sanctum's stock personal_access_tokens table uses
 * morphs('tokenable'), i.e. a BIGINT tokenable_id — but every Mindwell user id is a UUID,
 * so the app's login tokens could not be saved. This makes tokenable_id hold a UUID.
 *
 *  - Table missing          → create it the Sanctum way, with uuidMorphs.
 *  - Table empty (normal)   → drop and recreate with uuidMorphs.
 *  - Table has rows already → change the column type in place.
 *  - Already a UUID/string  → nothing to do.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->create();

            return;
        }

        $type = strtolower((string) Schema::getColumnType('personal_access_tokens', 'tokenable_id'));
        if (! in_array($type, ['bigint', 'integer', 'int', 'biginteger', 'smallint', 'mediumint'], true)) {
            return; // already char/uuid/varchar
        }

        if (DB::table('personal_access_tokens')->count() === 0) {
            Schema::drop('personal_access_tokens');
            $this->create();

            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('tokenable_id', 36)->change();
        });
    }

    private function create(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Leave the table as it is: going back to BIGINT would break UUID tokens.
    }
};
