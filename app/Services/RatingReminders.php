<?php

namespace App\Services;

use App\Models\SessionRating;
use App\Models\TherapySession;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one "How was your appointment?" email (added 2026-10-04), the day after a session
 * the client took part in, if they haven't rated or skipped it. Runs inside
 * `mindwell:notify`. Each session is stamped (rating_reminder_sent_at) so it's never
 * sent twice.
 */
class RatingReminders
{
    /** Wait this long after the session's planned end. */
    private const AFTER_HOURS = 18;

    public function __construct(private Notifier $notifier) {}

    public function run(): int
    {
        $sent = 0;

        TherapySession::whereNull('rating_reminder_sent_at')
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNotNull('client_joined_at')->orWhere('status', 'completed'))
            ->whereBetween('scheduled_at', [now()->subDays(SessionRating::ASK_FOR_DAYS), now()->subHours(self::AFTER_HOURS)])
            ->whereDoesntHave('rating')
            ->get()
            ->each(function (TherapySession $session) use (&$sent) {
                try {
                    if ($session->plannedEndAt()->gt(now()->subHours(self::AFTER_HOURS))) {
                        return; // not "the next day" yet
                    }

                    $session->forceFill(['rating_reminder_sent_at' => now()])->saveQuietly();
                    $this->notifier->rateSession($session);
                    $sent++;
                } catch (Throwable $e) {
                    Log::warning('Rating reminder failed', ['therapy_session_id' => $session->id, 'error' => $e->getMessage()]);
                }
            });

        return $sent;
    }
}
