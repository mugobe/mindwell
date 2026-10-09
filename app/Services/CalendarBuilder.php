<?php

namespace App\Services;

use App\Models\ScheduleChangeRequest;
use App\Models\TherapySession;
use App\Models\TimeOff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the day / week / month calendar (2026-10-08, roadmap 75) for one therapist or,
 * for admin, any set of therapists. Pure data — the Blade partial calendar._grid draws it.
 *
 * Each event: ['id','kind'=>'session'|'timeoff','start','end','title','sub','tone','session'|'timeOff','pendingChange']
 */
class CalendarBuilder
{
    public const VIEWS = ['day', 'week', 'month'];

    public function build(string $view, ?string $date, ?array $therapistProfileIds, bool $showTherapistName): array
    {
        $view = in_array($view, self::VIEWS, true) ? $view : 'week';
        $anchor = rescue(fn () => Carbon::parse($date ?: 'today'), Carbon::today(), false)->startOfDay();

        [$from, $to] = match ($view) {
            'day' => [$anchor->copy(), $anchor->copy()->addDay()],
            'week' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->startOfWeek(Carbon::MONDAY)->addWeek()],
            default => [$anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->addSecond()],
        };

        $duration = (int) config('mindwell.session_duration_minutes', 70);

        $sessions = TherapySession::with('clientProfile.user', 'therapistProfile.user', 'venue')
            ->when($therapistProfileIds !== null, fn ($q) => $q->whereIn('therapist_profile_id', $therapistProfileIds))
            ->whereIn('status', ['scheduled', 'pending_approval', 'completed'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $from->copy()->subMinutes($duration))
            ->where('scheduled_at', '<', $to)
            ->orderBy('scheduled_at')
            ->get();

        $pending = ScheduleChangeRequest::whereIn('therapy_session_id', $sessions->pluck('id'))
            ->where('status', ScheduleChangeRequest::PENDING)->get()->keyBy('therapy_session_id');

        $timeOff = TimeOff::with('therapistProfile.user')
            ->when($therapistProfileIds !== null, fn ($q) => $q->whereIn('therapist_profile_id', $therapistProfileIds))
            ->whereIn('status', [TimeOff::APPROVED, TimeOff::PENDING])
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->get();

        $events = collect();
        foreach ($sessions as $s) {
            $client = $s->clientProfile?->user;
            $events->push([
                'id' => $s->id,
                'kind' => 'session',
                'start' => $s->scheduled_at->copy(),
                'end' => $s->scheduled_at->copy()->addMinutes($s->duration_minutes ?: $duration),
                'title' => ($client?->name ?? 'Client').($showTherapistName ? ' · '.($s->therapistProfile?->user?->name ?? '—') : ''),
                'sub' => $s->isInPerson() ? 'In person'.($s->venue ? ' · '.$s->venue->name : '') : 'Video',
                'tone' => match (true) {
                    $s->status === 'completed' => 'done',
                    $s->status === 'pending_approval' => 'waiting',
                    isset($pending[$s->id]) => 'changing',
                    $s->isInPerson() => 'inperson',
                    default => 'video',
                },
                'session' => $s,
                'pendingChange' => $pending[$s->id] ?? null,
            ]);
        }
        foreach ($timeOff as $t) {
            $events->push([
                'id' => $t->id,
                'kind' => 'timeoff',
                'start' => $t->starts_at->copy(),
                'end' => $t->ends_at->copy(),
                'title' => 'Time off'.($showTherapistName ? ' · '.($t->therapistProfile?->user?->name ?? '—') : ''),
                'sub' => $t->status === TimeOff::PENDING ? 'Waiting for approval' : 'Approved',
                'tone' => $t->status === TimeOff::PENDING ? 'off-pending' : 'off',
                'timeOff' => $t,
                'pendingChange' => null,
            ]);
        }

        // Days in range, each with its events (time off spanning days appears on each day).
        $days = collect();
        for ($d = $from->copy(); $d->lt($to); $d->addDay()) {
            $dayStart = $d->copy();
            $dayEnd = $d->copy()->addDay();
            $dayEvents = $events->filter(fn ($e) => $e['start']->lt($dayEnd) && $e['end']->gt($dayStart))
                ->sortBy(fn ($e) => $e['start']->getTimestamp())->values();
            $days->push([
                'date' => $dayStart,
                'events' => $this->lanes($dayEvents, $dayStart),
                'inMonth' => $view !== 'month' || $dayStart->month === $anchor->month,
            ]);
        }

        return [
            'view' => $view,
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'events' => $events,
            'title' => match ($view) {
                'day' => $anchor->format('l, j F Y'),
                'week' => $from->format('j M').' – '.$to->copy()->subDay()->format('j M Y'),
                default => $anchor->format('F Y'),
            },
            'prev' => match ($view) {
                'day' => $anchor->copy()->subDay(), 'week' => $anchor->copy()->subWeek(), default => $anchor->copy()->subMonthNoOverflow(),
            },
            'next' => match ($view) {
                'day' => $anchor->copy()->addDay(), 'week' => $anchor->copy()->addWeek(), default => $anchor->copy()->addMonthNoOverflow(),
            },
            'hourStart' => (int) config('mindwell.calendar.day_start_hour', 7),
            'hourEnd' => (int) config('mindwell.calendar.day_end_hour', 21),
        ];
    }

    /** Side-by-side columns for overlapping events within one day (for the time grid). */
    private function lanes(Collection $events, Carbon $dayStart): Collection
    {
        $laneEnds = [];
        $placed = $events->map(function ($e) use (&$laneEnds, $dayStart) {
            $s = $e['start']->lt($dayStart) ? $dayStart->copy() : $e['start'];
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane]->gt($s)) {
                $lane++;
            }
            $laneEnds[$lane] = $e['end'];
            $e['lane'] = $lane;

            return $e;
        });
        $count = max(1, count($laneEnds));

        return $placed->map(fn ($e) => $e + ['lanes' => $count]);
    }
}
