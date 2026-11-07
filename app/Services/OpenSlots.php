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
        $step = max(10, (int) config('mindwell.slot_step_minutes', 30));

        $weekly = $therapistProfile->availabilitySlots()->where('is_recurring', true)->get()->groupBy('day_of_week');
        // One-off blocks (2026-11-07): only on their own date.
        $oneOff = $therapistProfile->availabilitySlots()->where('is_recurring', false)->whereNotNull('on_date')
            ->whereBetween('on_date', [$now->toDateString(), $until->toDateString()])->get()
            ->groupBy(fn ($s) => $s->on_date->toDateString());

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

            $blocks = $weekly->get(strtolower($date->format('l')), collect())->concat($oneOff->get($date->toDateString(), collect()));
            foreach ($blocks as $slot) {
                // 2026-10-19: every start time that fits in the block (every
                // slot_step_minutes), not only the block's first minute — so a
                // 9:00–17:00 block still offers times later today after 9:00 has passed.
                $blockStart = $date->copy()->setTimeFromTimeString((string) $slot->start_time);
                $blockEnd = filled($slot->end_time) ? $date->copy()->setTimeFromTimeString((string) $slot->end_time) : null;
                if ($blockEnd && $blockEnd->lte($blockStart)) {
                    $blockEnd = $blockStart->copy()->endOfDay(); // e.g. 18:00–00:00 runs to midnight
                }

                for ($start = $blockStart->copy(); ; $start = $start->copy()->addMinutes($step)) {
                    $end = $start->copy()->addMinutes($duration);

                    // The block's first start is always offered (as before, even for a
                    // block shorter than a session); later starts must fit in the block.
                    if (! $start->equalTo($blockStart) && (! $blockEnd || $end->gt($blockEnd))) {
                        break;
                    }

                    if ($start->lte($now)) {
                        if (! $blockEnd) {
                            break;
                        }

                        continue;
                    }

                    $clash = $busy->contains(function ($s) use ($start, $end, $duration) {
                        $sEnd = $s->scheduled_at->copy()->addMinutes($s->duration_minutes ?: $duration);

                        return $s->scheduled_at->lt($end) && $sEnd->gt($start);
                    });

                    $off = $timeOff->contains(fn ($t) => $t->starts_at->lt($end) && $t->ends_at->gt($start));

                    if (! $clash && ! $off) {
                        $slots->put($start->getTimestamp(), $start);
                    }

                    if (! $blockEnd) {
                        break;
                    }
                }
            }
        }

        return $slots->sortKeys()->values();
    }
}
