<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crisis_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->foreignUuid('intake_assessment_id')->nullable()->constrained('intake_assessments')->nullOnDelete();
            $table->string('reason');
            $table->enum('status', ['open', 'acknowledged', 'resolved'])->default('open');
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crisis_flags');
    }
};