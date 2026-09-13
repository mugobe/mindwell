<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('dob')->nullable();
            $table->string('timezone')->nullable();
            $table->uuid('current_subscription_id')->nullable();
            // FK to subscriptions.id added later, once the Commerce domain exists
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};