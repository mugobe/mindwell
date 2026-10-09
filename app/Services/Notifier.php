<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ClientSubscription;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\MindwellNotice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every reminder and notification Mindwell sends, in one place (added 2026-09-30).
 * Email always (if the person hasn't turned it off); SMS via Pandora for the
 * time-sensitive ones (confirmed, declined, reminders, "your therapist is ready") if
 * they have a phone number and haven't turned SMS off. New-message alerts are email
 * only — an SMS per message would be noisy and costs money.
 *
 * DISCREET by decision (2026-09-30): subject lines and SMS text stay neutral ("your
 * appointment", never "therapy session"); no message text, notes or anything clinical
 * is ever included; the other person's name only appears inside the email body.
 *
 * Nothing here ever throws — a mail or SMS failure is logged and the triggering action
 * (approving a booking, joining a room…) carries on regardless.
 */
class Notifier
{
    public function __construct(private PandoraSms $sms) {}

    /** Status just became 'scheduled' (approved, assigned, or matched from the lobby). */
    public function sessionConfirmed(TherapySession $session): void
    {
        $session->loadMissing('clientProfile.user', 'therapistProfile.user');
        $client = $session->clientProfile?->user;
        $therapist = $session->therapistProfile?->user;
        $at = $session->scheduled_at;

        // In-person (2026-10-07): its own wording, with where to go and no video link.
        if ($session->isInPerson()) {
            $this->inPersonConfirmed($session, $client, $therapist, 'confirmed');

            return;
        }

        // Matched from the lobby for right now: the client is already watching the
        // lobby screen, so only the therapist needs a nudge.
        $isNow = ! $at || $at->lte(now()->addMinutes(15));

        if ($therapist) {
            if ($isNow) {
                $this->deliver($therapist,
                    new MindwellNotice(
                        'A client is waiting for you',
                        $this->hi($therapist),
                        ['A client has just been matched with you and is waiting now.'],
                        'Open the session room',
                        route('sessions.room', $session),
                    ),
                    'Mindwell: a client has been matched with you and is waiting now. Open Mindwell to join.',
                );
            } else {
                $this->deliver($therapist,
                    new MindwellNotice(
                        'New appointment confirmed',
                        $this->hi($therapist),
                        ['An appointment with '.$this->first($client).' is confirmed for '.$this->when($at).'.'],
                        'Open your sessions',
                        route('therapist.sessions.index'),
                    ),
                    'Mindwell: new appointment confirmed for '.$this->short($at).'.',
                );
            }
        }

        if ($client && ! $isNow) {
            $this->deliver($client,
                new MindwellNotice(
                    'Your appointment is confirmed',
                    $this->hi($client),
                    [
                        'Your appointment with '.($therapist?->name ?? 'your therapist').' is confirmed for '.$this->when($at).'.',
                        "You'll get a reminder beforehand, and you can join from ".config('mindwell.video_join_early_minutes', 10).' minutes before it starts.',
                    ],
                    'View appointment',
                    route('sessions.connected', $session),
                ),
                'Mindwell: your appointment on '.$this->short($at).' is confirmed. We will remind you before it starts.',
            );
        }
    }

    /** Admin changed where a confirmed in-person session happens (2026-10-07). */
    public function sessionLocationChanged(TherapySession $session): void
    {
        $session->loadMissing('clientProfile.user', 'therapistProfile.user', 'venue');
        $this->inPersonConfirmed($session, $session->clientProfile?->user, $session->therapistProfile?->user, 'moved');
    }

    /** @param 'confirmed'|'moved' $why */
    private function inPersonConfirmed(TherapySession $session, ?User $client, ?User $therapist, string $why): void
    {
        $session->loadMissing('venue');
        $at = $session->scheduled_at;
        $where = $this->whereLines($session);

        if ($therapist) {
            $this->deliver($therapist,
                new MindwellNotice(
                    $why === 'moved' ? 'Change of place for an appointment' : 'New in-person appointment confirmed',
                    $this->hi($therapist),
                    array_merge([
                        ($why === 'moved' ? 'The in-person appointment with ' : 'An in-person appointment with ')
                            .$this->first($client).' on '.$this->when($at).($why === 'moved' ? ' has a new location.' : ' is confirmed.'),
                    ], $where),
                    'Open your sessions',
                    route('therapist.sessions.index'),
                ),
                'Mindwell: in-person appointment '.($why === 'moved' ? 'location changed' : 'confirmed').' for '.$this->short($at).'. Details in your email.',
            );
        }

        if ($client) {
            $this->deliver($client,
                new MindwellNotice(
                    $why === 'moved' ? 'Your appointment has a new location' : 'Your appointment is confirmed',
                    $this->hi($client),
                    array_merge([
                        'Your in-person appointment with '.($therapist?->name ?? 'your therapist').' on '.$this->when($at)
                            .($why === 'moved' ? ' will now take place here:' : ' is confirmed. Here is where to go:'),
                    ], $where, ['Please arrive a few minutes early. If you can no longer make it, let us know as soon as you can.']),
                    'View appointment',
                    route('sessions.connected', $session),
                ),
                'Mindwell: your appointment on '.$this->short($at).($why === 'moved' ? ' has a new location' : ' is confirmed').'. Details in your email.',
            );
        }
    }

    /** Where an in-person session happens, as email lines. */
    private function whereLines(TherapySession $session): array
    {
        $session->loadMissing('venue');
        $venue = $session->venue;
        $lines = [];

        if ($venue) {
            $lines[] = 'Where: '.$venue->label();
            if ($venue->directions) {
                $lines[] = 'Directions: '.$venue->directions;
            }
            if ($venue->map_url) {
                $lines[] = 'Map: '.$venue->map_url;
            }
            if ($venue->phone) {
                $lines[] = 'Reception: '.$venue->phone;
            }
        }
        if (filled($session->location_details)) {
            $lines[] = ($venue ? 'Details: ' : 'Where: ').$session->location_details;
        }
        if (! $lines) {
            $lines[] = "Where: our team will confirm the place shortly.";
        }

        return $lines;
    }

    // ------------------------------------------------------------------
    // Calendar: reschedule / cancel requests and time off (2026-10-08, roadmap 75).

    /** A client or therapist asked to move or cancel a session — tell the admins. */
    public function scheduleRequestSubmitted(\App\Models\ScheduleChangeRequest $request, iterable $admins): void
    {
        $request->loadMissing('therapySession.clientProfile.user', 'therapySession.therapistProfile.user');
        $session = $request->therapySession;
        $who = $request->requester_role === 'client'
            ? ($session?->clientProfile?->user?->name ?? 'A client')
            : ($session?->therapistProfile?->user?->name ?? 'A therapist');

        foreach ($admins as $admin) {
            $this->deliver($admin,
                new MindwellNotice(
                    'Schedule change request',
                    $this->hi($admin),
                    array_values(array_filter([
                        $who.' ('.strtolower($request->requesterLabel()).') asked to '
                            .($request->type === 'cancel' ? 'cancel' : 'move').' the session on '.$this->when($session?->scheduled_at).'.',
                        $request->type === 'reschedule' ? 'Proposed new time: '.$this->when($request->proposed_at).'.' : null,
                        $request->reason ? 'Reason: '.$request->reason : null,
                    ])),
                    'Review requests',
                    route('admin.schedule-requests.index'),
                ),
                null,
            );
        }
    }

    /** Admin declined a request — tell whoever asked. */
    public function scheduleRequestDeclined(\App\Models\ScheduleChangeRequest $request): void
    {
        $request->loadMissing('requester', 'therapySession');
        $to = $request->requester;
        if (! $to || $request->requester_role === 'admin') {
            return;
        }

        $this->deliver($to,
            new MindwellNotice(
                'About your request to '.($request->type === 'cancel' ? 'cancel' : 'move').' an appointment',
                $this->hi($to),
                array_values(array_filter([
                    'Your request about the appointment on '.$this->when($request->therapySession?->scheduled_at)." couldn't be approved, so it stays as it is.",
                    $request->decision_note ? 'Note from the team: '.$request->decision_note : null,
                ])),
                'Open Mindwell',
                $request->requester_role === 'therapist' ? route('therapist.calendar') : route('sessions.history'),
            ),
            'Mindwell: your request about the appointment on '.$this->short($request->therapySession?->scheduled_at)." couldn't be approved. Details in your email.",
        );
    }

    /** A confirmed session moved to a new time — tell both people. */
    public function sessionRescheduled(TherapySession $session, ?CarbonInterface $oldAt): void
    {
        $session->loadMissing('clientProfile.user', 'therapistProfile.user', 'venue');
        $client = $session->clientProfile?->user;
        $therapist = $session->therapistProfile?->user;
        $where = $session->isInPerson() ? $this->whereLines($session) : [];

        foreach ([[$client, 'client'], [$therapist, 'therapist']] as [$to, $role]) {
            if (! $to) {
                continue;
            }
            $other = $role === 'client' ? ($therapist?->name ?? 'your therapist') : $this->first($client);
            $this->deliver($to,
                new MindwellNotice(
                    'Your appointment has a new time',
                    $this->hi($to),
                    array_merge([
                        'Your appointment with '.$other.' has moved'.($oldAt ? ' from '.$this->when($oldAt) : '').' to '.$this->when($session->scheduled_at).'.',
                    ], $where),
                    'View appointment',
                    $role === 'client' ? route('sessions.connected', $session) : route('therapist.calendar'),
                ),
                'Mindwell: your appointment has moved to '.$this->short($session->scheduled_at).'.',
            );
        }
    }

    /** A confirmed session was cancelled — tell both people. */
    public function sessionCancelled(TherapySession $session, array $outcome = []): void
    {
        $session->loadMissing('clientProfile.user', 'therapistProfile.user');
        $client = $session->clientProfile?->user;
        $therapist = $session->therapistProfile?->user;

        if ($client) {
            $this->deliver($client,
                new MindwellNotice(
                    'Your appointment is cancelled',
                    $this->hi($client),
                    array_values(array_filter([
                        'Your appointment with '.($therapist?->name ?? 'your therapist').' on '.$this->when($session->scheduled_at).' has been cancelled.',
                        ! empty($outcome['credit_returned']) ? 'The session credit has been added back to your plan.' : null,
                        ! empty($outcome['refunded']) ? 'Your payment is being refunded — you will get a separate email about it.' : null,
                        'You can book another time whenever you are ready.',
                    ])),
                    'Book another time',
                    route('therapists.browse'),
                ),
                'Mindwell: your appointment on '.$this->short($session->scheduled_at).' has been cancelled.',
            );
        }

        if ($therapist) {
            $this->deliver($therapist,
                new MindwellNotice(
                    'Appointment cancelled',
                    $this->hi($therapist),
                    ['The appointment with '.$this->first($client).' on '.$this->when($session->scheduled_at).' has been cancelled.'],
                    'Open your calendar',
                    route('therapist.calendar'),
                ),
                'Mindwell: the appointment on '.$this->short($session->scheduled_at).' has been cancelled.',
            );
        }
    }

    /** A therapist asked for time off — tell the admins. */
    public function timeOffRequested(\App\Models\TimeOff $timeOff, iterable $admins): void
    {
        $timeOff->loadMissing('therapistProfile.user');
        foreach ($admins as $admin) {
            $this->deliver($admin,
                new MindwellNotice(
                    'Time off request',
                    $this->hi($admin),
                    array_values(array_filter([
                        ($timeOff->therapistProfile?->user?->name ?? 'A therapist').' asked for time off: '.$timeOff->rangeLabel().'.',
                        $timeOff->reason ? 'Reason: '.$timeOff->reason : null,
                    ])),
                    'Review requests',
                    route('admin.schedule-requests.index'),
                ),
                null,
            );
        }
    }

    /** Admin approved or declined time off — tell the therapist. */
    public function timeOffDecided(\App\Models\TimeOff $timeOff): void
    {
        $timeOff->loadMissing('therapistProfile.user');
        $therapist = $timeOff->therapistProfile?->user;
        if (! $therapist) {
            return;
        }

        $approved = $timeOff->status === \App\Models\TimeOff::APPROVED;
        $this->deliver($therapist,
            new MindwellNotice(
                $approved ? 'Your time off is approved' : 'About your time off request',
                $this->hi($therapist),
                array_values(array_filter([
                    'Your time off ('.$timeOff->rangeLabel().') has been '.($approved ? 'approved. Clients can\'t book you during it.' : 'declined.'),
                    $timeOff->decision_note ? 'Note from the team: '.$timeOff->decision_note : null,
                ])),
                'Open your calendar',
                route('therapist.calendar'),
            ),
            null,
        );
    }

    /** A booking waiting for approval was declined by admin/routing. */
    public function bookingDeclined(TherapySession $session): void
    {
        $session->loadMissing('clientProfile.user');
        $client = $session->clientProfile?->user;
        if (! $client) {
            return;
        }

        $this->deliver($client,
            new MindwellNotice(
                'About your appointment request',
                $this->hi($client),
                [
                    'Unfortunately, the appointment you asked for on '.$this->when($session->scheduled_at)." couldn't be confirmed.",
                    'Please choose another time that suits you.',
                ],
                'Choose another time',
                route('therapists.browse'),
            ),
            "Mindwell: your appointment request for {$this->short($session->scheduled_at)} couldn't be confirmed. Please open Mindwell to choose another time.",
        );
    }

    /** @param '24h'|'1h' $which */
    public function reminder(TherapySession $session, string $which): void
    {
        $session->loadMissing('clientProfile.user', 'therapistProfile.user');
        $at = $session->scheduled_at;
        $client = $session->clientProfile?->user;
        $therapist = $session->therapistProfile?->user;

        foreach ([[$client, $therapist, 'client'], [$therapist, $client, 'therapist']] as [$to, $other, $role]) {
            if (! $to) {
                continue;
            }

            $otherName = $role === 'client' ? ($other?->name ?? 'your therapist') : $this->first($other);

            if ($session->isInPerson()) {
                // In-person (2026-10-07): where to go instead of how to join.
                $lines = ['A reminder of your in-person appointment with '.$otherName.' '.$this->relative($at).'.'];
                $lines = array_merge($lines, $this->whereLines($session));
                $url = $role === 'client' ? route('sessions.connected', $session) : route('therapist.sessions.index');
                $mail = $which === '24h'
                    ? new MindwellNotice('Reminder: your appointment '.$this->relativeShort($at), $this->hi($to), $lines, 'View appointment', $url)
                    : new MindwellNotice('Your appointment starts at '.$at->format('g:i A'), $this->hi($to), $lines, 'View appointment', $url);
                // SMS stays discreet (no place names) — the address is in the email.
                $sms = 'Mindwell reminder: your in-person appointment is '.$this->relativeShort($at).'. Details in your email.';
                $this->deliver($to, $mail, $sms);

                continue;
            }

            if ($which === '24h') {
                $lines = ['A reminder of your appointment with '.$otherName.' '.$this->relative($at).'.'];
                if ($role === 'client') {
                    $lines[] = "When the time comes, find a private, quiet spot — headphones help. You can join from "
                        .config('mindwell.video_join_early_minutes', 10).' minutes before.';
                }
                $mail = new MindwellNotice('Reminder: your appointment '.$this->relativeShort($at), $this->hi($to), $lines,
                    'Open Mindwell', route('sessions.room', $session));
                $sms = 'Mindwell reminder: your appointment is '.$this->relativeShort($at).'.';
            } else {
                $mail = new MindwellNotice('Your appointment starts at '.$at->format('g:i A'), $this->hi($to),
                    ['Your appointment with '.$otherName.' starts at '.$at->format('g:i A').'. You can join from '
                        .$at->copy()->subMinutes((int) config('mindwell.video_join_early_minutes', 10))->format('g:i A').'.'],
                    'Go to the session room', route('sessions.room', $session));
                $sms = 'Mindwell: your appointment starts at '.$at->format('g:i A').'. Open Mindwell to join.';
            }

            $this->deliver($to, $mail, $sms);
        }
    }

    /** The therapist has opened the room and the client isn't there yet. */
    public function roomReady(TherapySession $session): void
    {
        $session->loadMissing('clientProfile.user', 'therapistProfile.user');
        $client = $session->clientProfile?->user;
        if (! $client) {
            return;
        }

        $this->deliver($client,
            new MindwellNotice(
                "It's time to join",
                $this->hi($client),
                [($session->therapistProfile?->user?->name ?? 'Your therapist').' is ready and waiting for you in your session room.'],
                'Join now',
                route('sessions.room', $session),
            ),
            'Mindwell: your appointment is ready for you now. Open Mindwell to join.',
        );
    }

    /** Email only (see class docblock). */
    public function newMessages(ChatConversation $conversation, string $recipientRole, int $count): void
    {
        $conversation->loadMissing('clientProfile.user', 'therapistProfile.user');
        $to = $recipientRole === 'client' ? $conversation->clientProfile?->user : $conversation->therapistProfile?->user;
        $from = $recipientRole === 'client' ? $conversation->therapistProfile?->user : $conversation->clientProfile?->user;
        if (! $to) {
            return;
        }

        $fromName = $recipientRole === 'client' ? ($from?->name ?? 'your therapist') : $this->first($from);

        $this->deliver($to,
            new MindwellNotice(
                'You have a new message',
                $this->hi($to),
                ['You have '.($count === 1 ? 'a new message' : "{$count} new messages").' from '.$fromName.' on Mindwell.'],
                'Read '.($count === 1 ? 'message' : 'messages'),
                route('messages.show', $conversation),
            ),
            null,
        );
    }

    // ---- Subscription renewals (added 2026-10-01). Client only. Same discreet rule:
    // "your Mindwell plan", never what it's for.

    /** A few days before the renewal date, if they haven't renewed yet. */
    public function planRenewsSoon(ClientSubscription $subscription): void
    {
        $subscription->loadMissing('clientProfile.user', 'subscriptionPlan');
        $client = $subscription->clientProfile?->user;
        if (! $client) {
            return;
        }

        $renewsOn = $subscription->current_period_end;
        $left = (int) $subscription->sessions_remaining;
        $lines = ['Your '.($subscription->subscriptionPlan?->name ?? 'Mindwell').' plan renews on '.$renewsOn->format('l, M j').'. Renew before then to keep booking without a break.'];
        if ($left > 0) {
            $lines[] = 'You still have '.$left.' '.($left === 1 ? 'credit' : 'credits').' this period. Unused credits don\'t carry over, so now is a good time to book.';
        }

        $this->deliver($client,
            new MindwellNotice('Your Mindwell plan renews on '.$renewsOn->format('D j M'), $this->hi($client), $lines,
                'Renew my plan', route('subscription.renew')),
            'Mindwell: your plan renews on '.$renewsOn->format('D j M').'. Renew in the app to keep booking.'
                .($left > 0 ? ' You have '.$left.' '.($left === 1 ? 'credit' : 'credits').' left.' : ''),
        );
    }

    /** On the renewal date (start of the grace period). */
    public function planRenewalDue(ClientSubscription $subscription): void
    {
        $subscription->loadMissing('clientProfile.user', 'subscriptionPlan');
        $client = $subscription->clientProfile?->user;
        if (! $client) {
            return;
        }

        $lastDay = $subscription->lastUsableDay();

        $this->deliver($client,
            new MindwellNotice('Your Mindwell plan is due for renewal', $this->hi($client), [
                'Your '.($subscription->subscriptionPlan?->name ?? 'Mindwell').' plan was due for renewal on '.$subscription->current_period_end->format('l, M j').'.',
                'You can keep using it until '.$lastDay->format('l, M j').'. Renew before then to keep your plan.',
            ], 'Renew my plan', route('subscription.renew')),
            'Mindwell: your plan is due for renewal. Renew by '.$lastDay->format('D j M').' to keep booking.',
        );
    }

    /** The grace period ran out without a renewal. */
    public function planExpired(ClientSubscription $subscription): void
    {
        $subscription->loadMissing('clientProfile.user', 'subscriptionPlan');
        $client = $subscription->clientProfile?->user;
        if (! $client) {
            return;
        }

        $this->deliver($client,
            new MindwellNotice('Your Mindwell plan has ended', $this->hi($client), [
                'Your '.($subscription->subscriptionPlan?->name ?? 'Mindwell').' plan has ended.',
                'Whenever you\'re ready you can pick a plan again from My plan, or pay for a single appointment when you book.',
            ], 'Go to My plan', route('subscription.index')),
            'Mindwell: your plan has ended. You can renew any time in the app.',
        );
    }

    // ---- Progress check-ins (added 2026-10-03).

    /** Two weeks (plus a couple of days) since their last check-in. Client only. */
    public function checkInReady(User $client): void
    {
        $this->deliver($client,
            new MindwellNotice('A quick check-in is ready for you', $this->hi($client), [
                'It\'s been about two weeks since your last check-in. It takes around 3 minutes and helps you and your therapist see how things are going.',
            ], 'Start my check-in', route('progress.check-in')),
            'Mindwell: a quick 3-minute check-in is ready for you in the app.',
        );
    }

    /**
     * A check-in answer needs urgent follow-up (PHQ-9 item 9). Goes to the client's
     * therapist and every admin, ALWAYS — notification preferences don't apply to
     * safety alerts. Still discreet: no name, no clinical detail, just "open Mindwell".
     *
     * @param  iterable<User>  $admins
     */
    public function crisisAlert(?User $therapist, iterable $admins, ?string $therapistUrl, ?string $adminUrl = null, string $source = 'check-in'): void
    {
        $lines = [
            'A client answered a safety question on their '.$source.' in a way that needs follow-up today.',
            'For privacy this message doesn\'t say who. Please open Mindwell to see the details.',
        ];
        $sms = 'Mindwell URGENT: a client needs follow-up today. Please open Mindwell now.';

        $sent = [];

        if ($therapist) {
            $this->deliverUrgent($therapist,
                new MindwellNotice('Urgent: a client needs follow-up', $this->hi($therapist), $lines,
                    'Open their progress page', $therapistUrl ?? route('therapist.sessions.index'), null),
                $sms);
            $sent[$therapist->id] = true;
        }

        foreach ($admins as $admin) {
            if (isset($sent[$admin->id])) {
                continue;
            }
            $this->deliverUrgent($admin,
                new MindwellNotice('Urgent: a client needs follow-up', $this->hi($admin), $lines,
                    'Open their progress page', $adminUrl ?? route('admin.crisis.index'), null),
                $sms);
            $sent[$admin->id] = true;
        }
    }

    // ---- Session feedback (added 2026-10-04). Email only, never SMS.

    /** The day after a session they took part in, if they haven't rated it yet. */
    public function rateSession(TherapySession $session): void
    {
        $session->loadMissing('clientProfile.user', 'therapistProfile.user');
        $client = $session->clientProfile?->user;
        if (! $client) {
            return;
        }

        $this->deliver($client,
            new MindwellNotice('How was your Mindwell appointment?', $this->hi($client), [
                'We\'d love to hear how your appointment with '.($session->therapistProfile?->user?->name ?? 'your therapist')
                    .' on '.$session->scheduled_at->format('l, M j').' went.',
                'It takes a few seconds. Only the Mindwell team reads your comments — your therapist doesn\'t see them.',
            ], 'Share my feedback', route('sessions.rate', $session)),
            null,
        );
    }

    /**
     * A session was rated at or below SessionRating::LOW — tell the admins so someone can
     * follow up. Email only; respects their email switch.
     *
     * @param  iterable<User>  $admins
     */
    public function lowRating(\App\Models\SessionRating $rating, iterable $admins): void
    {
        foreach ($admins as $admin) {
            $this->deliver($admin,
                new MindwellNotice('A session received a low rating', $this->hi($admin), array_values(array_filter([
                    'A client rated a recent session '.$rating->rating.' out of 5.',
                    $rating->comment ? 'They also left a comment.' : null,
                    'You may want to check in with the client or the therapist.',
                ])), 'Open session ratings', route('admin.ratings.index', ['low' => 1])),
                null,
            );
        }
    }

    // ---- Admin money tools & employer reminders (added 2026-10-06). Email only.

    /** A refund was recorded by an admin — tell the client. */
    public function refundRecorded(\App\Models\PaymentTransaction $transaction): void
    {
        $transaction->loadMissing('clientProfile.user', 'therapySession.therapistProfile.user', 'subscriptionPlan');
        $client = $transaction->clientProfile?->user;
        if (! $client || blank($client->email)) {
            return;
        }

        $amount = $transaction->currency.' '.number_format((float) $transaction->refund_amount, 0);

        try {
            // Like receipts: always sent (it's about their money), not subject to the email switch.
            $client->notify(new MindwellNotice(
                'Your Mindwell refund',
                $this->hi($client),
                array_values(array_filter([
                    'We\'ve refunded '.$amount.' for '.lcfirst($transaction->receiptDescription()).($transaction->receipt_number ? ' (receipt '.$transaction->receipt_number.')' : '').'.',
                    $transaction->isCash()
                        ? 'The Mindwell team will arrange to return it to you directly.'
                        : 'It can take a few working days to reach your Mobile Money account.',
                    'If you have any questions, just reply to this email.',
                ])),
                'View your payments',
                route('payments.index'),
                'Keep this email for your records.',
            ));
        } catch (Throwable $e) {
            Log::warning('Refund email failed', ['payment_transaction_id' => $transaction->id, 'error' => $e->getMessage()]);
        }
    }

    /** An employer seat got a new code — send it to the employer's contact person. */
    public function employerSeatReissued(\App\Models\EmployerAccount $employer, string $code): bool
    {
        if (blank($employer->contact_email)) {
            return false;
        }

        try {
            \Illuminate\Support\Facades\Notification::route('mail', $employer->contact_email)->notify(new MindwellNotice(
                'A new Mindwell access code for '.$employer->company_name,
                'Hi '.($employer->contact_name ? trim(explode(' ', $employer->contact_name)[0]) : 'there').',',
                [
                    'One of your Mindwell staff places has a new access code: '.$code,
                    'Give this code to the staff member who should use the place. They enter it under "Employer benefit" after signing up. The previous code no longer works.',
                ],
                null,
                null,
                'Please keep access codes private — anyone with a code can use the place.',
            ));

            return true;
        } catch (Throwable $e) {
            Log::warning('Employer code email failed', ['employer_account_id' => $employer->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * An employer contract renews/ends soon — tell the admins.
     *
     * @param  iterable<User>  $admins
     */
    public function employerContractEnding(\App\Models\EmployerAccount $employer, iterable $admins): void
    {
        $days = (int) floor(now()->startOfDay()->diffInDays($employer->contract_renewal_date->copy()->startOfDay(), true));

        foreach ($admins as $admin) {
            $this->deliver($admin,
                new MindwellNotice('Employer contract ending: '.$employer->company_name, $this->hi($admin), [
                    $employer->company_name.'\'s Mindwell contract ends on '.$employer->contract_renewal_date->format('l, j F Y').' — in '.$days.' '.($days === 1 ? 'day' : 'days').'.',
                    'After that date their staff can no longer book with their employer benefit. Contact '.($employer->contact_name ?: 'the employer').($employer->contact_email ? ' ('.$employer->contact_email.')' : '').' about renewing, then update the renewal date.',
                ], 'Open employer', route('admin.employers.show', $employer)),
                null,
            );
        }
    }

    /** Sends a one-off test SMS (settings page / artisan command). */
    public function testSms(string $normalizedNumber): array
    {
        return $this->sms->send($normalizedNumber, 'Mindwell: this is a test message. SMS reminders are working.');
    }

    // ------------------------------------------------------------------

    private function deliver(User $user, MindwellNotice $mail, ?string $sms): void
    {
        if (($user->notify_email ?? true) && filled($user->email)) {
            try {
                $user->notify($mail);
            } catch (Throwable $e) {
                Log::warning('Notification email failed', ['user_id' => $user->id, 'subject' => $mail->subject, 'error' => $e->getMessage()]);
            }
        }

        if ($sms && ($user->notify_sms ?? true) && filled($user->phone)) {
            $this->sms->send($user->phone, $sms);
        }
    }

    /** Like deliver(), but ignores the person's email/SMS switches — safety alerts only. */
    private function deliverUrgent(User $user, MindwellNotice $mail, string $sms): void
    {
        if (filled($user->email)) {
            try {
                $user->notify($mail);
            } catch (Throwable $e) {
                Log::error('URGENT notification email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        if (filled($user->phone)) {
            $result = $this->sms->send($user->phone, $sms);
            if (! ($result['ok'] ?? false)) {
                Log::error('URGENT notification SMS failed', ['user_id' => $user->id, 'detail' => $result['detail'] ?? null]);
            }
        }
    }

    private function hi(?User $user): string
    {
        return 'Hi '.$this->first($user).',';
    }

    private function first(?User $user): string
    {
        $name = trim((string) $user?->name);

        return $name === '' ? 'there' : explode(' ', $name)[0];
    }

    private function when(?CarbonInterface $at): string
    {
        return $at ? $at->format('l, M j \a\t g:i A') : 'the agreed time';
    }

    private function short(?CarbonInterface $at): string
    {
        return $at ? $at->format('D j M, g:i A') : 'the agreed time';
    }

    private function relative(CarbonInterface $at): string
    {
        return $at->isToday() ? 'today at '.$at->format('g:i A')
            : ($at->isTomorrow() ? 'tomorrow at '.$at->format('g:i A') : 'on '.$this->when($at));
    }

    private function relativeShort(CarbonInterface $at): string
    {
        return $at->isToday() ? 'today at '.$at->format('g:i A')
            : ($at->isTomorrow() ? 'tomorrow at '.$at->format('g:i A') : 'on '.$this->short($at));
    }
}
