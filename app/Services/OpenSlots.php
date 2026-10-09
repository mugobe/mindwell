<?php

namespace App\Services;

use App\Models\TherapySession;
use App\Models\TimeOff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A therapist's bookable times (2026-10-08, roadmap 75): their weekly availability
 * projected forward, minus anything already booked or waiting for approval at an
 * overlapping time, minus APPROVED time off. Used by the client booking page, the
 * client's reschedule request and the therapist's follow-up picker, so all three agree.
 */
class OpenSlots
{
    /** @return Collection<int, Carbon> */
    public function for($therapistProfile, int $days = 7, ?string $ignoreSessionId = null): Collection
    {
        $now = Carbon::now();
        $until = $now->copy()->addDays($days)->endOfDay();
        $duration = (int) config('mindwell.session_duration_minutes', 70);

        $weekly = $therapistProfile->availabilitySlots()->where('is_recurring', true)->get()->groupBy('day_of_week');

        $busy = TherapySession::where('therapist_profile_id', $therapistProfile->id)
            ->whereIn('status', ['scheduled', 'pending_approval', 'pending_payment'])
            ->whereBetween('scheduled_at', [$now->copy()->subMinutes($duration), $until])
            ->when($ignoreSessionId, fn ($q) => $q->where('id', '!=', $ignoreSessionId))
            ->get(['id', 'scheduled_at', 'duration_minutes', 'status', 'created_at'])
            // An unpaid booking only holds its slot for a short while.
            ->reject(fn ($s) => $s->status === 'pending_payment' && $s->created_at->lt($now->copy()->subMinutes(30)));

        $timeOff = TimeOff::blocking($therapistProfile->id, $now, $until)->get(['starts_at', 'ends_at']);

        $slots = collect();
        for ($d = 0; $d < $days; $d++) {
            $date = $now->copy()->addDays($d);

            foreach ($weekly->get(strtolower($date->format('l')), collect()) as $slot) {
                $start = $date->copy()->setTimeFromTimeString((string) $slot->start_time);
                $end = $start->copy()->addMinutes($duration);

                if ($start->lte($now)) {
                    continue;
                }

                $clash = $busy->contains(function ($s) use ($start, $end, $duration) {
                    $sEnd = $s->scheduled_at->copy()->addMinutes($s->duration_minutes ?: $duration);

                    return $s->scheduled_at->lt($end) && $sEnd->gt($start);
                });

                $off = $timeOff->contains(fn ($t) => $t->starts_at->lt($end) && $t->ends_at->gt($start));

                if (! $clash && ! $off) {
                    $slots->push($start);
                }
            }
        }

        return $slots->sort()->values();
    }
}
