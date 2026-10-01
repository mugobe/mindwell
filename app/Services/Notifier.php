<?php

namespace App\Services;

use App\Models\ChatConversation;
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
