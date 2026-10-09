<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\ClientProfile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "A quick check-in is ready" email + SMS (added 2026-10-03). Runs inside
 * `mindwell:notify`, so no new scheduler line is needed.
 *
 * The dashboard card is the first invitation; this nudge only goes out once a client
 * in care is config('mindwell.check_in_reminder_delay_days') days past due, and only
 * once per check-in cycle (users.check_in_reminded_at is compared with their last
 * check-in/intake).
 */
class CheckInReminders
{
    public function __construct(private Notifier $notifier) {}

    public function run(): int
    {
        $sent = 0;
        $delay = (int) config('mindwell.check_in_reminder_delay_days', 2);

        foreach (CheckIn::inCareProfileIds()->chunk(100) as $ids) {
            ClientProfile::with('user')->whereIn('id', $ids->all())->get()->each(function ($profile) use ($delay, &$sent) {
                try {
                    $user = $profile->user;
                    if (! $user) {
                        return;
                    }

                    $status = CheckIn::statusFor($profile);
                    if (! $status['due'] || $status['overdue_days'] < $delay) {
                        return;
                    }

                    // Already nudged for this cycle?
                    if ($user->check_in_reminded_at && $user->check_in_reminded_at->gte($status['last_at'])) {
                        return;
                    }

                    $user->forceFill(['check_in_reminded_at' => now()])->saveQuietly();
                    $this->notifier->checkInReady($user);
                    $sent++;
                } catch (Throwable $e) {
                    Log::warning('Check-in reminder failed', ['client_profile_id' => $profile->id, 'error' => $e->getMessage()]);
                }
            });
        }

        return $sent;
    }
}
