<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->enum('status', ['active', 'expired', 'canceled'])->default('active');
            // Remaining session credits for the CURRENT billing period. Decremented every time
            // a credit is used instead of a one-off payment; refilled to the plan's
            // sessions_included when a new period starts (period rollover isn't automated yet —
            // see AdminClientSubscriptionController note).
            $table->unsignedInteger('sessions_remaining')->default(0);
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_subscriptions');
    }
};
