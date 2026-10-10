<?php

namespace App\Support;

use App\Models\ClientSubscription;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * The JSON shapes the mobile app sees (API v1, 2026-10-09). One place, so every endpoint
 * returns a session / therapist / payment the same way. Dates are ISO-8601 with the
 * app's timezone offset; money is a number in config('flutterwave.currency').
 *
 * Privacy: nothing here exposes therapist-only material (closure reports, outcomes,
 * risk levels, raw screening scores) — same rule as the client's web pages.
 */
class ApiPresenter
{
    public static function date($value): ?string
    {
        return $value?->toIso8601String();
    }

    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone ? UgandaPhone::display($user->phone) : null,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'gender' => $user->gender,
            'preferred_language' => $user->preferred_language,
            'emergency_contact' => [
                'name' => $user->emergency_contact_name,
                'phone' => $user->emergency_contact_phone ? UgandaPhone::display($user->emergency_contact_phone) : null,
                'relationship' => $user->emergency_contact_relationship,
            ],
            'client_label' => rescue(fn () => $user->clientLabel(), null, false),
            'notify_email' => (bool) ($user->notify_email ?? true),
            'notify_sms' => (bool) ($user->notify_sms ?? false),
            'share_mood_with_therapist' => (bool) $user->share_mood_with_therapist,
            'must_change_password' => (bool) $user->must_change_password,
            'consent_required' => ! $user->hasAcceptedCurrentLegal(),
        ];
    }

    public static function photoUrl(?TherapistProfile $t): ?string
    {
        return $t?->photo_path ? Storage::disk('public')->url($t->photo_path) : null;
    }

    public static function therapist(?TherapistProfile $t, bool $full = false): ?array
    {
        if (! $t) {
            return null;
        }

        $out = [
            'id' => $t->id,
            'name' => $t->user?->name ?? 'Therapist',
            'title' => $t->professional_title,
            'photo_url' => self::photoUrl($t),
            'specialties' => is_array($t->specialties) ? array_values($t->specialties) : (filled($t->specialties) ? [(string) $t->specialties] : []),
            'languages' => $t->languages,
            'offers_in_person' => (bool) $t->offers_in_person,
        ];

        if ($full) {
            $out['bio'] = $t->bio;
            $out['qualifications'] = $t->qualifications;
        }

        return $out;
    }

    /**
     * What the app should show / do next for a session. Mirrors the web's
     * SessionRequestController::routeForStatus().
     */
    public static function nextStep(TherapySession $s): string
    {
        return match ($s->status) {
            'pending_payment' => 'checkout',
            'routing' => 'lobby',
            'pending_approval' => 'awaiting_approval',
            'scheduled' => $s->isInPerson() ? 'in_person' : 'video',
            'completed' => 'completed',
            default => 'none',
        };
    }

    public static function session(TherapySession $s): array
    {
        $statusLabel = [
            'pending_payment' => 'Waiting for payment',
            'routing' => 'Finding your therapist',
            'pending_approval' => 'Waiting for confirmation',
            'scheduled' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ][$s->status] ?? ucfirst(str_replace('_', ' ', (string) $s->status));

        return [
            'id' => $s->id,
            'status' => $s->status,
            'status_label' => $statusLabel,
            'next_step' => self::nextStep($s),
            'mode' => $s->session_mode ?: TherapySession::MODE_VIDEO,
            'scheduled_at' => self::date($s->scheduled_at),
            'ends_at' => self::date($s->plannedEndAt()),
            'duration_minutes' => (int) ($s->duration_minutes ?: config('mindwell.session_duration_minutes', 70)),
            'session_number' => $s->session_number,
            'therapist' => self::therapist($s->relationLoaded('therapistProfile') ? $s->therapistProfile : $s->therapistProfile()->with('user')->first()),
            'location' => $s->isInPerson() ? [
                'text' => $s->locationText(),
                'venue' => $s->venue ? [
                    'name' => $s->venue->name,
                    'address' => $s->venue->address,
                    'directions' => $s->venue->directions ?? null,
                    'map_url' => $s->venue->map_url,
                    'phone' => $s->venue->phone,
                ] : null,
            ] : null,
            'price' => $s->status === 'pending_payment' ? $s->listPrice() : null,
            'amount_charged' => $s->amount_charged !== null ? (float) $s->amount_charged : null,
            'payment_method' => $s->payment_method,
            'can_change' => $s->status === 'scheduled' && (bool) $s->scheduled_at?->isFuture(),
            'can_rate' => $s->isRateable() && ! $s->rating()->exists(),
            'created_at' => self::date($s->created_at),
        ];
    }

    public static function transaction(PaymentTransaction $p): array
    {
        return [
            'id' => $p->id,
            'reference' => $p->tx_ref,
            'status' => $p->status,
            'kind' => $p->kindLabel(),
            'description' => $p->receiptDescription(),
            'amount' => (float) $p->amount,
            'original_amount' => $p->original_amount !== null ? (float) $p->original_amount : null,
            'discount' => $p->discount_amount !== null ? (float) $p->discount_amount : null,
            'refunded' => $p->refund_amount !== null ? (float) $p->refund_amount : null,
            'currency' => $p->currency,
            'method' => $p->method_label,
            'receipt_number' => $p->receipt_number,
            'has_receipt' => $p->status === 'successful',
            'instruction' => $p->meta['instruction'] ?? null,
            'paid_at' => self::date($p->paidAt()),
            'created_at' => self::date($p->created_at),
            'session_id' => $p->therapy_session_id,
        ];
    }

    public static function plan(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'price' => (float) $plan->price,
            'billing_interval' => $plan->billing_interval,
            'sessions_included' => (int) $plan->sessions_included,
            'features' => array_values((array) $plan->features),
        ];
    }

    public static function subscription(?ClientSubscription $sub): ?array
    {
        if (! $sub) {
            return null;
        }

        $phase = $sub->phase();

        return [
            'id' => $sub->id,
            'plan' => $sub->subscriptionPlan ? self::plan($sub->subscriptionPlan) : null,
            'status' => $sub->status,
            'phase' => $phase,
            'sessions_remaining' => (int) $sub->sessions_remaining,
            'period_start' => $sub->current_period_start?->toDateString(),
            'period_end' => $sub->current_period_end?->toDateString(),
            'last_usable_day' => rescue(fn () => $sub->lastUsableDay()->toDateString(), null, false),
            'renewal_paid' => $sub->renewal_paid_at !== null,
            'can_renew' => $sub->status === 'active' && ! $sub->renewal_paid_at && $sub->canRenew(),
        ];
    }

    /** The voucher quote, without the Voucher model. */
    public static function quote(array $q): array
    {
        return [
            'ok' => (bool) $q['ok'],
            'applied' => (bool) $q['applied'],
            'code' => $q['code'],
            'error' => $q['error'],
            'original' => (float) $q['original'],
            'discount' => (float) $q['discount'],
            'total' => (float) $q['total'],
            'free' => $q['applied'] && $q['total'] <= 0,
            'currency' => config('flutterwave.currency'),
        ];
    }
}
