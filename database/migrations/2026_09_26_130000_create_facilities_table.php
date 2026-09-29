<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The registry of external hospitals/clinics/specialists the routing team can refer a
 * client out to (2026-09-26 client request, following up on therapist session closure).
 * `facility_type` is deliberately free text, not a fixed enum -- there's no confirmed
 * list of categories yet (rehab, psychiatric, general, etc.), just examples given so far.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('facility_type')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
