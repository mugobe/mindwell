<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employer_account_id')->constrained('employer_accounts')->cascadeOnDelete();
            $table->foreignUuid('client_profile_id')->nullable()->constrained('client_profiles')->nullOnDelete();
            $table->string('seat_code')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_seats');
    }
};