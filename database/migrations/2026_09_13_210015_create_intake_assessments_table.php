<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->json('responses');
            $table->unsignedTinyInteger('phq9_score')->nullable();
            $table->unsignedTinyInteger('gad7_score')->nullable();
            $table->enum('risk_level', ['low', 'moderate', 'high', 'crisis'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_assessments');
    }
};