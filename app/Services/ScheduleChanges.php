<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClientSubscription;
use App\Models\PaymentTransaction;
use App\Models\ScheduleChangeRequest as Req;
use App\Models\TherapySession;
use App\Models\TimeOff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving and cancelling sessions, and time off (2026-10-08, roadmap 75). Decided with
 * Collins: clients and therapists ASK, admin approves (and can also change things
 * directly); therapists ask for time off, admin approves. What happens to the money on
 * a cancellation is pre-ticked from config('mindwell.cancellation') and admin can change
 * it each time.
 */
class ScheduleChanges
{
    public function __construct(private Notifier $notifier) {}

    // ---------------------------------------------------------------- requests

    public function request(Request $http, TherapySession $session, User $by, string $role, string $type, ?Carbon $proposedAt, ?string $reason): Req
    {
        $this->assertChangeable($session);

        if (Req::where('therapy_session_id', $session->id)->where('status', Req::PENDING)->exists()) {
            throw ValidationException::withMessages(['type' => 'There is already a request waiting for this session. The team will look at it shortly.']);
        }

        if ($type === Req::TYPE_RESCHEDULE) {
            $this->assertNewTime($session, $proposedAt);
        }

        $req = Req::create([
            'therapy_session_id' => $session->id,
            'requested_by' => $by->id,
            'requester_role' => $role,
            'type' => $type,
            'current_at' => $session->scheduled_at,
            'proposed_at' => $type === Req::TYPE_RESCHEDULE ? $proposedAt : null,
            'reason' => filled($reason) ? trim($reason) : null,
            'status' => Req::PENDING,
        ]);

        AuditLog::record($http, 'session.change_requested', ucfirst($role).' asked to '.($type === 'cancel' ? 'cancel' : 'move').' a session.', [
            'therapy_session_id' => $session->id, 'schedule_change_request_id' => $req->id,
        ]);

        $this->notifier->scheduleRequestSubmitted($req, User::where('role', 'admin')->get());

        return $req;
    }

    public function withdraw(Request $http, Req $req): void
    {
        abort_unless($req->isPending(), 409, 'This request has already been decided.');
        $req->update(['status' => Req::WITHDRAWN, 'decided_at' => now()]);
        AuditLog::record($http, 'session.change_withdrawn', 'Withdrew a schedule change request.', ['schedule_change_request_id' => $req->id]);
    }

    /** Pre-ticked money choices for cancelling (see config/mindwell.php → cancellation). */
    public function moneyDefaults(TherapySession $session, ?Carbon $askedAt = null): array
    {
        $askedAt ??= now();
        $onTime = $session->scheduled_at && $askedAt->diffInMinutes($session->scheduled_at, false) >= 60 * (int) config('mindwell.cancellation.notice_hours', 24);
        $pick = fn (string $rule) => match ($rule) {
            'always' => true,
            'on_time' => $onTime,
            default => false,
        };

        $payment = $this->paymentFor($session);

        return [
            'on_time' => $onTime,
            'has_credit' => $session->payment_method === 'credit',
            'has_payment' => (bool) $payment,
            'payment' => $payment,
            'return_credit' => $session->payment_method === 'credit' && $pick((string) config('mindwell.cancellation.return_credit', 'always')),
            'refund' => (bool) $payment && $pick((string) config('mindwell.cancellation.refund_payment', 'on_time')),
        ];
    }

    /** @param array{return_credit?: bool, refund?: bool, note?: ?string, proposed_at?: ?Carbon} $options */
    public function approve(Request $http, Req $req, User $admin, array $options = []): void
    {
        // Checked again under a row lock inside the transaction, so a double-click can't
        // apply the same approval twice (double credit / double refund).
        DB::transaction(function () use ($http, $req, $admin, $options) {
            $req = Req::whereKey($req->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($req->isPending(), 409, 'This request has already been decided.');

            $session = TherapySession::whereKey($req->therapy_session_id)->lockForUpdate()->first();
            $this->assertChangeable($session);

            // Admin may approve a move with a different time than was asked for.
            $newAt = $req->type === Req::TYPE_RESCHEDULE ? ($options['proposed_at'] ?? $req->proposed_at) : null;
            if ($req->type === Req::TYPE_RESCHEDULE) {
                $this->assertNewTime($session, $newAt, allowClash: true);
            }

            $this->applyApproval($http, $req, $session, $admin, $options, $newAt);
        });
    }

    public function decline(Request $http, Req $req, User $admin, ?string $note): void
    {
        $declined = DB::transaction(function () use ($req, $admin, $note) {
            $req = Req::whereKey($req->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($req->isPending(), 409, 'This request has already been decided.');
            $req->update(['status' => Req::DECLINED, 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_note' => $note]);

            return $req;
        });

        AuditLog::record($http, 'session.change_declined', 'Declined: '.$declined->summary().'.', ['schedule_change_request_id' => $declined->id]);
        $this->notifier->scheduleRequestDeclined($declined);
    }

    /**
     * Admin changes a session straight away — recorded as an already-approved request.
     * A move onto a time the therapist isn't free needs $options['allow_clash'].
     */
    public function direct(Request $http, TherapySession $session, User $admin, string $type, ?Carbon $newAt, ?string $reason, array $options = []): void
    {
        DB::transaction(function () use ($http, $session, $admin, $type, $newAt, $reason, $options) {
            $session = TherapySession::whereKey($session->getKey())->lockForUpdate()->first();

            // Everything is checked before anything is written.
            $this->assertChangeable($session);
            if ($type === Req::TYPE_RESCHEDULE) {
                $this->assertNewTime($session, $newAt, allowClash: (bool) ($options['allow_clash'] ?? false),
                    clashMessage: 'The therapist is not free then (another booking or approved time off). Pick another time, or tick “Book anyway”.');
            }

            // Any waiting request for this session is overtaken by the admin's change.
            $overtaken = Req::where('therapy_session_id', $session->id)->where('status', Req::PENDING)->get();
            foreach ($overtaken as $old) {
                $old->update(['status' => Req::DECLINED, 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_note' => 'Overtaken by a change made by the team.']);
            }

            $req = Req::create([
                'therapy_session_id' => $session->id,
                'requested_by' => $admin->id,
                'requester_role' => 'admin',
                'type' => $type,
                'current_at' => $session->scheduled_at,
                'proposed_at' => $type === Req::TYPE_RESCHEDULE ? $newAt : null,
                'reason' => $reason,
                'status' => Req::PENDING,
            ]);

            $this->applyApproval($http, $req, $session, $admin, $options, $newAt);
        });
    }

    /** Inside a transaction: apply the change and mark the request approved. Emails go after commit. */
    private function applyApproval(Request $http, Req $req, TherapySession $session, User $admin, array $options, ?Carbon $newAt): void
    {
        $outcome = $req->type === Req::TYPE_RESCHEDULE
            ? $this->applyReschedule($session, $newAt)
            : $this->applyCancel($http, $session, $admin, $options);

        $req->update([
            'status' => Req::APPROVED,
            'proposed_at' => $newAt ?? $req->proposed_at,
            'decided_by' => $admin->id,
            'decided_at' => now(),
            'decision_note' => $options['note'] ?? null,
            'outcome' => $outcome,
        ]);

        AuditLog::record($http, 'session.change_approved', 'Approved: '.$req->summary().'.', [
            'therapy_session_id' => $session->id, 'schedule_change_request_id' => $req->id, 'outcome' => $outcome,
        ]);
    }

    // ---------------------------------------------------------------- time off

    public function requestTimeOff(Request $http, $therapistProfile, Carbon $from, Carbon $to, ?string $reason): TimeOff
    {
        if ($to->lte($from)) {
            throw ValidationException::withMessages(['ends_at' => 'The end must be after the start.']);
        }
        if ($to->lte(now())) {
            throw ValidationException::withMessages(['ends_at' => 'Time off must be in the future.']);
        }

        $timeOff = TimeOff::create([
            'therapist_profile_id' => $therapistProfile->id,
            'starts_at' => $from,
            'ends_at' => $to,
            'reason' => filled($reason) ? trim($reason) : null,
            'status' => TimeOff::PENDING,
        ]);

        AuditLog::record($http, 'time_off.requested', 'Asked for time off: '.$timeOff->rangeLabel().'.', ['time_off_id' => $timeOff->id]);
        $this->notifier->timeOffRequested($timeOff, User::where('role', 'admin')->get());

        return $timeOff;
    }

    public function decideTimeOff(Request $http, TimeOff $timeOff, User $admin, bool $approve, ?string $note): void
    {
        abort_unless($timeOff->status === TimeOff::PENDING, 409, 'This time off has already been decided.');

        $timeOff->update([
            'status' => $approve ? TimeOff::APPROVED : TimeOff::DECLINED,
            'decided_by' => $admin->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        AuditLog::record($http, $approve ? 'time_off.approved' : 'time_off.declined', ($approve ? 'Approved' : 'Declined').' time off: '.$timeOff->rangeLabel().'.', ['time_off_id' => $timeOff->id]);
        $this->notifier->timeOffDecided($timeOff);
    }

    // ---------------------------------------------------------------- internals

    private function assertChangeable(?TherapySession $session): void
    {
        abort_unless($session && $session->status === 'scheduled', 409, 'Only confirmed sessions can be moved or cancelled.');
        // A session whose time has passed can only be closed out, not moved or cancelled.
        abort_unless($session->scheduled_at?->isFuture(), 409, 'This session has already started or passed, so it can no longer be moved or cancelled.');
    }

    private function assertNewTime(TherapySession $session, ?Carbon $at, bool $allowClash = false, string $clashMessage = 'The therapist is not free then. Please pick another time.'): void
    {
        if (! $at || $at->lte(now())) {
            throw ValidationException::withMessages(['proposed_at' => 'Pick a new time in the future.']);
        }
        if ($session->scheduled_at && $at->equalTo($session->scheduled_at)) {
            throw ValidationException::withMessages(['proposed_at' => 'That is the same time it is already booked for.']);
        }
        if (! $allowClash && $this->clashes($session, $at)) {
            throw ValidationException::withMessages(['proposed_at' => $clashMessage]);
        }
    }

    /** Does $at overlap another booking or approved time off for this session's therapist? */
    public function clashes(TherapySession $session, Carbon $at): bool
    {
        $duration = $session->duration_minutes ?: (int) config('mindwell.session_duration_minutes', 70);
        $end = $at->copy()->addMinutes($duration);

        $booked = TherapySession::where('therapist_profile_id', $session->therapist_profile_id)
            ->where('id', '!=', $session->id)
            ->whereBetween('scheduled_at', [$at->copy()->subMinutes($duration)->addSecond(), $end->copy()->subSecond()])
            ->where(fn ($q) => $q->whereIn('status', ['scheduled', 'pending_approval'])
                // An unpaid booking holds its time for 30 minutes (same as OpenSlots).
                ->orWhere(fn ($w) => $w->where('status', 'pending_payment')->where('created_at', '>=', now()->subMinutes(30))))
            ->exists();

        return $booked || TimeOff::blocking($session->therapist_profile_id, $at, $end)->exists();
    }

    private function applyReschedule(TherapySession $session, Carbon $newAt): array
    {
        $oldAt = $session->scheduled_at?->copy();

        // Same status, so the "confirmed" email doesn't fire; reminders start over.
        $session->forceFill([
            'scheduled_at' => $newAt,
            'reminder_24h_sent_at' => null,
            'reminder_1h_sent_at' => null,
            'room_alert_sent_at' => null,
        ])->save();

        DB::afterCommit(fn () => $this->notifier->sessionRescheduled($session->fresh(), $oldAt));

        return ['moved_from' => $oldAt?->toDateTimeString(), 'moved_to' => $newAt->toDateTimeString()];
    }

    private function applyCancel(Request $http, TherapySession $session, User $admin, array $options): array
    {
        $outcome = ['credit_returned' => false, 'refunded' => false];

        $session->update(['status' => 'cancelled']);

        if (! empty($options['return_credit']) && $session->payment_method === 'credit') {
            $subscription = ClientSubscription::where('client_profile_id', $session->client_profile_id)
                ->where('status', 'active')->orderByDesc('current_period_start')->first();
            if ($subscription) {
                $subscription->increment('sessions_remaining');
                $outcome['credit_returned'] = true;
            } else {
                $outcome['credit_note'] = 'No active plan to return the credit to.';
            }
        }

        if (! empty($options['refund']) && ($payment = $this->paymentFor($session))) {
            $payment->forceFill([
                'refunded_at' => now(),
                'refund_amount' => $payment->amount,
                'refund_reason' => 'Session cancelled',
                'refunded_by' => $admin->id,
            ])->save();
            $outcome['refunded'] = true;
            $outcome['refund_amount'] = (float) $payment->amount;

            AuditLog::record($http, 'payment.refunded', 'Recorded a refund of '.$payment->currency.' '.number_format((float) $payment->amount, 0)
                .' on '.($payment->receipt_number ?? $payment->tx_ref).' (session cancelled).', ['payment_transaction_id' => $payment->id]);
            DB::afterCommit(fn () => $this->notifier->refundRecorded($payment));
        }

        DB::afterCommit(fn () => $this->notifier->sessionCancelled($session, $outcome));

        return $outcome;
    }

    /** The paid (not yet refunded, non-zero) Mobile Money/cash payment for a session. */
    private function paymentFor(TherapySession $session): ?PaymentTransaction
    {
        return PaymentTransaction::where('therapy_session_id', $session->id)
            ->where('purpose', 'session')
            ->where('status', 'successful')
            ->whereNull('refunded_at')
            ->where('amount', '>', 0)
            ->latest()
            ->first();
    }
}
