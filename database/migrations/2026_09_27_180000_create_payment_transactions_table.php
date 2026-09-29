<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real Flutterwave integration (2026-09-27) — see App\Models\PaymentTransaction for
 * why this is its own table rather than columns bolted onto therapy_sessions/
 * client_subscriptions, and App\Http\Controllers\FlutterwavePaymentController for how
 * a row here gets confirmed and turned into a paid session or a real subscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->enum('purpose', ['session', 'subscription']);

            // Exactly one of these is set, depending on `purpose` — enforced in code
            // (SessionRequestController::pay() / SubscriptionController::subscribe()),
            // not at the DB level, matching how this codebase handles the other
            // one-of-several-nullable-FKs cases already (e.g. therapy_sessions'
            // intake_assessment_id vs previous_session_id).
            $table->foreignUuid('therapy_session_id')->nullable()->constrained('therapy_sessions')->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->nullable()->constrained('subscription_plans')->cascadeOnDelete();

            // Our own reference, generated before ever calling Flutterwave — unique per
            // attempt, so a retried/abandoned payment doesn't collide with a fresh one.
            $table->string('tx_ref')->unique();
            // Flutterwave's own transaction id, filled in only once verify() confirms it.
            $table->string('flw_transaction_id')->nullable();

            $table->enum('status', ['pending', 'successful', 'failed', 'cancelled'])->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);

            // The raw verify()/webhook response, kept for audit/debugging — never the
            // source of truth for what happened, just a record of what Flutterwave said.
            $table->json('meta')->nullable();

            // Idempotency guard: the redirect callback and the webhook can both arrive
            // for the same charge, and only whichever one sees this still null is
            // allowed to actually create the ClientSubscription / mark the session paid.
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
