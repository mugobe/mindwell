<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Optional journal reminders (2026-11-07). Reminders instead of limits: a client can
 * ask for a morning nudge ("How are you feeling today?") and/or an evening one
 * ("Anything you want to write down?"). Off by default; email only (free and private).
 *
 *   morning  08:00–08:59, skipped if today's mood is already in
 *   evening  19:30–20:29, skipped if they checked in or wrote since 3 PM
 *
 * Runs from mindwell:notify every 5 minutes; a cache key per person, day and time of day
 * makes sure each reminder goes out at most once.
 */
class JournalReminders
{
    public const CHOICES = ['morning', 'evening', 'both'];

    public function __construct(private Notifier $notifier) {}

    public function run(?Carbon $now = null): int
    {
        $now ??= now();
        $minutes = $now->hour * 60 + $now->minute;
        $slot = match (true) {
            $minutes >= 8 * 60 && $minutes < 9 * 60 => 'morning',
            $minutes >= 19 * 60 + 30 && $minutes < 20 * 60 + 30 => 'evening',
            default => null,
        };
        if (! $slot) {
            return 0;
        }

        $sent = 0;
        User::where('role', 'client')
            ->whereIn('journal_reminders', [$slot, 'both'])
            ->where('notify_email', true)
            ->whereNotNull('email')
            ->with('clientProfile')
            ->chunkById(200, function ($users) use ($slot, $now, &$sent) {
                foreach ($users as $user) {
                    $profile = $user->clientProfile;
                    if (! $profile || $this->alreadyDone($profile->id, $slot, $now)) {
                        continue;
                    }
                    if (! Cache::add("journal-reminder:{$user->id}:{$now->toDateString()}:{$slot}", 1, $now->copy()->addDays(2))) {
                        continue;
                    }
                    $this->notifier->journalReminder($user, $slot);
                    $sent++;
                }
            });

        return $sent;
    }

    private function alreadyDone(string $profileId, string $slot, Carbon $now): bool
    {
        $q = JournalEntry::where('client_profile_id', $profileId)->where('kind', 'daily');

        return $slot === 'morning'
            ? $q->whereNotNull('mood')->where('created_at', '>=', $now->copy()->startOfDay())->exists()
            : $q->where('created_at', '>=', $now->copy()->setTime(15, 0))->exists();
    }
}
