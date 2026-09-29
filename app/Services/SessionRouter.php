<?php

namespace App\Services;

use App\Models\TherapistProfile;
use App\Models\TherapySession;
use Illuminate\Support\Carbon;

/**
 * V1 routing heuristic — deliberately simple, and simplifications are called out below.
 * This is the "algorithmic" half of the flow the client asked for: "first attempt via
 * automatic/algorithmic matching, falling back to a staff member manually routing them."
 *
 * Simplifications (known, not hidden):
 * - No matching by specialty/category yet. Intake currently only produces a PHQ-9/GAD-7
 *   risk level, not a "concern type," so there's nothing to match specialties against yet.
 *   This picks any verified therapist who is open right now.
 * - "Busy" is approximated as "has a scheduled session that started within the last
 *   duration_minutes" — there's no real calendar/conflict engine here.
 * - Picks whichever eligible therapist has the fewest sessions today, as a basic
 *   load-balancing tiebreaker, not anything more sophisticated.
 */
class SessionRouter
{
    public function attempt(TherapySession $session): bool
    {
        $now = Carbon::now();
        $today = strtolower($now->format('l'));

        $candidates = TherapistProfile::where('credentials_verified', true)
            ->whereHas('availabilitySlots', function ($q) use ($today, $now) {
                $q->where('day_of_week', $today)
                    ->whereTime('start_time', '<=', $now->format('H:i:s'))
                    ->whereTime('end_time', '>=', $now->format('H:i:s'));
            })
            ->get()
            ->reject(fn (TherapistProfile $t) => $this->isBusyRightNow($t, $now));

        if ($candidates->isEmpty()) {
            return false;
        }

        $chosen = $candidates
            ->sortBy(fn (TherapistProfile $t) => TherapySession::where('therapist_profile_id', $t->id)
                ->whereDate('scheduled_at', $now->toDateString())
                ->count())
            ->first();

        $session->update([
            'therapist_profile_id' => $chosen->id,
            'status' => 'scheduled',
            'routing_method' => 'algorithmic',
            'scheduled_at' => $now,
            'routed_at' => $now,
        ]);

        return true;
    }

    private function isBusyRightNow(TherapistProfile $therapist, Carbon $now): bool
    {
        return TherapySession::where('therapist_profile_id', $therapist->id)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$now->copy()->subMinutes(70), $now])
            ->exists();
    }
}
