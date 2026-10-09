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
        'client_subscription_id',
        'tx_ref',
        'flw_transaction_id',
        'status',
        'amount',
        'currency',
        'meta',
        'processed_at',
        'receipt_number',
        'method_label',
        'receipt_emailed_at',
        // Refunds recorded by an admin (2026-10-06).
        'refunded_at',
        'refund_amount',
        'refund_reason',
        'refunded_by',
        // Vouchers (2026-10-07): the list price and how much the voucher took off.
        'voucher_id',
        'original_amount',
        'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
            'processed_at' => 'datetime',
            'receipt_emailed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
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

    /** Set only on a renewal payment (2026-10-01) — the plan being renewed. */
    public function clientSubscription(): BelongsTo
    {
        return $this->belongsTo(ClientSubscription::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function hasDiscount(): bool
    {
        return $this->voucher_id !== null && (float) $this->discount_amount > 0;
    }

    /** Paid nothing because a voucher covered it all (no Flutterwave charge). */
    public function isVoucherFree(): bool
    {
        return (bool) ($this->meta['voucher_free'] ?? false);
    }

    public function isRenewal(): bool
    {
        return $this->purpose === 'subscription' && $this->client_subscription_id !== null;
    }

    // ------------------------------------------------------------------
    // Receipts (added 2026-10-02) — wording decided with Collins: "Counselling session"
    // / "Counselling plan" on the receipt itself, while email subjects stay discreet.

    /** e.g. "MTN Mobile Money · 0772 ••• 456" — recorded when the charge starts. */
    public static function methodLabelFor(string $network, string $phoneNumber): string
    {
        $name = strtoupper($network) === 'AIRTEL' ? 'Airtel Money' : 'MTN Mobile Money';
        $normalized = \App\Support\UgandaPhone::normalize($phoneNumber);

        if (! $normalized) {
            return $name;
        }

        $local = '0'.substr($normalized, 3); // 0772123456

        return $name.' · '.substr($local, 0, 4).' ••• '.substr($local, -3);
    }

    /** The line item shown on the receipt. */
    public function receiptDescription(): string
    {
        if ($this->purpose === 'session') {
            $session = $this->therapySession;
            $therapist = $session?->therapistProfile?->user?->name;

            return 'Counselling session'
                .($session?->isInPerson() ? ' (in person)' : '')
                .($therapist ? ' with '.$therapist : '')
                .($session?->scheduled_at ? ', '.$session->scheduled_at->format('M j, Y') : '');
        }

        $plan = $this->subscriptionPlan;
        $planText = $plan
            ? $plan->name.' ('.$plan->sessions_included.' '.($plan->sessions_included === 1 ? 'session' : 'sessions')
                .' per '.($plan->billing_interval === 'monthly' ? 'month' : 'week').')'
            : 'Mindwell plan';

        return ($this->client_subscription_id ? 'Counselling plan renewal: ' : 'Counselling plan: ').$planText;
    }

    /** Short label for lists: "Session", "Plan", "Plan renewal". */
    public function kindLabel(): string
    {
        return match (true) {
            $this->purpose === 'session' => 'Session',
            $this->client_subscription_id !== null => 'Plan renewal',
            default => 'Plan',
        };
    }

    /** Sandbox / simulated payments are marked as tests on the receipt. */
    public function isTest(): bool
    {
        if ($this->isCash() || $this->isVoucherFree()) {
            return false; // no Flutterwave charge involved, so sandbox mode is irrelevant
        }

        return (bool) ($this->meta['simulated'] ?? false)
            || str_contains((string) config('flutterwave.base_url'), 'sandbox');
    }

    /** Recorded by an admin as paid in cash (2026-10-06). */
    public function isCash(): bool
    {
        return (bool) ($this->meta['cash'] ?? false);
    }

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }

    public function isFullyRefunded(): bool
    {
        return $this->isRefunded() && (float) $this->refund_amount >= (float) $this->amount - 0.01;
    }

    /** What we actually kept: amount minus any refund. */
    public function netAmount(): float
    {
        return max(0, (float) $this->amount - (float) ($this->refund_amount ?? 0));
    }

    public function paidAt(): ?\Illuminate\Support\Carbon
    {
        return $this->processed_at ?? ($this->status === 'successful' ? $this->updated_at : null);
    }
}
