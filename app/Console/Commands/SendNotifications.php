<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\TherapySession;
use App\Services\Notifier;
use Illuminate\Console\Command;

/**
 * The time-based notifications (added 2026-09-30). Meant to run every 5 minutes from the
 * scheduler — add this line to routes/console.php:
 *
 *     Schedule::command('mindwell:notify')->everyFiveMinutes();
 *
 * Locally, either keep `php artisan schedule:work` running in a second terminal, or
 * just run `php artisan mindwell:notify` by hand when testing.
 *
 * Each reminder/alert is stamped (…_sent_at / …_notified_at) so re-running this is
 * always safe — nothing goes out twice.
 */
class SendNotifications extends Command
{
    protected $signature = 'mindwell:notify';

    protected $description = 'Send appointment reminders and new-message emails that are due';

    public function handle(Notifier $notifier): int
    {
        $now = now();
        $sent24 = $sent1 = $sentMsg = 0;

        // ---- Day-before reminder: sessions starting within the next 24 hours.
        // Skipped (but stamped) for anything booked less than a day ahead or starting
        // within 2 hours — the confirmation / 1-hour reminder already cover those.
        TherapySession::where('status', 'scheduled')
            ->whereNull('reminder_24h_sent_at')
            ->whereBetween('scheduled_at', [$now, $now->copy()->addHours(24)])
            ->get()
            ->each(function (TherapySession $s) use ($notifier, $now, &$sent24) {
                $bookedFarAhead = $s->created_at && $s->scheduled_at->gt($s->created_at->copy()->addHours(25));
                $notImminent = $s->scheduled_at->gt($now->copy()->addHours(2));
                $s->forceFill(['reminder_24h_sent_at' => $now])->saveQuietly();
                if ($bookedFarAhead && $notImminent) {
                    $notifier->reminder($s, '24h');
                    $sent24++;
                }
            });

        // ---- One-hour reminder. Skipped (but stamped) for lobby matches and anything
        // booked less than 90 minutes ahead — those people are already on it.
        TherapySession::where('status', 'scheduled')
            ->whereNull('reminder_1h_sent_at')
            ->whereBetween('scheduled_at', [$now, $now->copy()->addMinutes(60)])
            ->get()
            ->each(function (TherapySession $s) use ($notifier, $now, &$sent1) {
                $bookedAhead = $s->created_at && $s->scheduled_at->gt($s->created_at->copy()->addMinutes(90));
                $s->forceFill(['reminder_1h_sent_at' => $now])->saveQuietly();
                if ($bookedAhead) {
                    $notifier->reminder($s, '1h');
                    $sent1++;
                }
            });

        // ---- "You have a new message": once per batch of unread messages, per side,
        // once the oldest unread one has sat unread for the configured delay.
        $delay = (int) config('mindwell.message_email_delay_minutes', 10);

        ChatConversation::whereNotNull('last_message_at')
            ->where('last_message_at', '>=', $now->copy()->subDays(3))
            ->where('last_message_at', '<=', $now->copy()->subMinutes($delay))
            ->get()
            ->each(function (ChatConversation $c) use ($notifier, $now, $delay, &$sentMsg) {
                foreach (['client', 'therapist'] as $role) {
                    $lastRead = $c->lastReadAtFor($role);
                    $notifiedColumn = $role.'_notified_at';

                    $unread = $c->messages()
                        ->where('sender_role', '!=', $role)
                        ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
                        ->orderBy('created_at')
                        ->get(['id', 'created_at']);

                    if ($unread->isEmpty()) {
                        continue;
                    }

                    $oldest = $unread->first()->created_at;
                    if ($oldest->gt($now->copy()->subMinutes($delay))) {
                        continue; // give them a chance to see it in the app first
                    }
                    if ($c->{$notifiedColumn} && $c->{$notifiedColumn}->gte($oldest)) {
                        continue; // already told them about this batch
                    }

                    $c->forceFill([$notifiedColumn => $now])->saveQuietly();
                    $notifier->newMessages($c, $role, $unread->count());
                    $sentMsg++;
                }
            });

        $this->info("Day-before reminders: {$sent24} · one-hour reminders: {$sent1} · new-message emails: {$sentMsg}");

        return self::SUCCESS;
    }
}
