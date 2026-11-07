{{-- The right action for a therapist's session, right now (2026-11-03 staff refresh).
     The orange Join button only shows when it's nearly time; earlier the same day there's
     a quieter "Open room" (therapists may open the room any time on the day, see
     VideoRoomController::access()), and on other days just a countdown chip.
     Usage: @include('partials.therapist-join', ['session' => $s]) --}}
@php
    $mwS = $session;
    $mwNow = now();
    $mwStart = $mwS->scheduled_at;
    $mwDur = $mwS->duration_minutes ?: (int) config('mindwell.session_duration_minutes', 70);
    $mwEnd = $mwStart?->copy()->addMinutes($mwDur);
    $mwCloses = $mwEnd?->copy()->addMinutes((int) config('mindwell.video_join_late_minutes', 30));
    $mwMins = $mwStart ? (int) floor($mwNow->diffInMinutes($mwStart, false)) : null;
    $mwIn = function (int $m) {
        if ($m <= 0) { return 'Now'; }
        if ($m < 60) { return 'In '.$m.' min'; }
        $h = intdiv($m, 60);

        return $h < 24 ? 'In '.$h.' h' : null;
    };
@endphp
<span style="display:inline-flex;align-items:center;gap:8px;white-space:nowrap;">
@if (! $mwStart)
    <span class="mw-chip grey">No time yet</span>
@elseif ($mwS->status === 'completed')
    <span class="mw-chip green">Closed</span>
@elseif ($mwS->isInPerson())
    @if ($mwEnd->isPast())
        <a href="{{ route('therapist.sessions.close', $mwS) }}" class="btn primary sm">Close out</a>
    @elseif ($mwMins <= 60)
        <span class="mw-chip live"><span class="dot"></span>{{ $mwIn(max(0, $mwMins)) }}</span><span class="mw-chip green">In person</span>
    @else
        <span class="mw-chip grey">{{ $mwStart->isToday() ? $mwIn($mwMins) : $mwStart->format('D g:i A') }}</span>
    @endif
@elseif ($mwCloses->isPast())
    <a href="{{ route('therapist.sessions.close', $mwS) }}" class="btn primary sm">Close out</a>
@elseif ($mwStart->isToday() && $mwMins <= 60)
    <span class="mw-chip live"><span class="dot"></span>{{ $mwEnd->isPast() ? 'Running late' : $mwIn(max(0, $mwMins)) }}</span>
    <a href="{{ route('sessions.room', $mwS) }}" class="btn sm" style="background:#EE7800;color:#fff;border:0;">Join</a>
@elseif ($mwStart->isToday())
    <span class="mw-chip grey">{{ $mwIn($mwMins) }}</span>
    <a href="{{ route('sessions.room', $mwS) }}" class="btn ghost sm" title="You can open the room early. The client can join from {{ config('mindwell.video_join_early_minutes', 10) }} minutes before.">Open room</a>
@else
    <span class="mw-chip grey">{{ $mwStart->isTomorrow() ? 'Tomorrow '.$mwStart->format('g:i A') : $mwStart->format('D j M') }}</span>
@endif
</span>
