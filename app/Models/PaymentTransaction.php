<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per Flutterwave payment ATTEMPT (real integration, 2026-09-27) — both the
 * one-off session-payment flow (SessionRequestController::pay()) and the subscription
 * self-checkout flow (SubscriptionController::subscribe()) create one of these, hand
 * its tx_ref to Flutterwave, then FlutterwavePaymentController::callback()/webhook()
 * look it back up by tx_ref once Flutterwave confirms the charge.
 *
 * Kept as its own table (rather than columns bolted onto therapy_sessions/
 * client_subscriptions) so a failed or abandoned attempt doesn't have to be squeezed
 * into either of those tables' own status enums, and so one shape covers both purposes
 * without a payable_id/payable_type polymorphic relation — this codebase doesn't use
 * polymorphic relations anywhere else, so two plain nullable foreign keys stays
 * consistent with its style.
 *
 * `processed_at` is the idempotency guard: the redirect callback and the webhook can
 * both arrive for the same charge (a client's browser redirect racing the server-to-
 * server webhook), and only the first one to see it still null is allowed to actually
 * create the ClientSubscription / mark the TherapySession paid — see
 * FlutterwavePaymentController::finalize().
 */
class PaymentTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_profile_id',
        'purpose',
        'therapy_session_id',
        'subscription_plan_id',
        'tx_ref',
        'flw_transaction_id',
        'status',
        'amount',
        'currency',
        'meta',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function therapySession(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
