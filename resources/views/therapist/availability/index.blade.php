@extends('layouts.app')
@section('title', 'Availability')
@section('content')
{{-- Therapist → Availability (2026-10-08 redesign, round 3): a week strip that shows each day's
     open hours on a 6 AM–10 PM bar, removable time chips, and a fold-away "Add hours" form.
     Data from Therapist\AvailabilityController::index(). --}}
@php
    $C = \Illuminate\Support\Carbon::class;
    $dayStart = 6 * 60;
    $dayEnd = 22 * 60;
    $span = $dayEnd - $dayStart;
    $mins = fn ($t) => (int) $C::parse($t)->format('G') * 60 + (int) $C::parse($t)->format('i');
    $weekMinutes = 0;
    foreach ($days as $d) {
        foreach ($slots->get($d, collect()) as $s) {
            if ($s->is_recurring) {
                $weekMinutes += max(0, $mins($s->end_time) - $mins($s->start_time));
            }
        }
    }
    $openDays = collect($days)->filter(fn ($d) => $slots->get($d, collect())->isNotEmpty())->count();
    $addOpen = $errors->has('day_of_week') || $errors->has('start_time') || $errors->has('end_time') || $slots->isEmpty();
@endphp

<div class="mw-page-head">
    <div>
        <h1>Availability</h1>
        <div class="sub">The hours clients and the team can book you. Need a day or a week off? Ask for time off from the calendar.</div>
    </div>
    <div class="actions">
        <a href="{{ route('therapist.calendar') }}" class="btn ghost sm">Calendar &amp; time off</a>
        <button type="button" class="btn primary sm" onclick="var d=document.getElementById('mw-add'); d.open=true; d.scrollIntoView({behavior:'smooth',block:'start'}); document.getElementById('day_of_week').focus();">Add hours</button>
    </div>
</div>

<div class="mw-kpis">
    <div class="mw-kpi"><div class="k">Open each week</div><div class="v">{{ rtrim(rtrim(number_format($weekMinutes / 60, 1), '0'), '.') }} h</div><div class="d">Repeating hours only</div></div>
    <div class="mw-kpi"><div class="k">Days you work</div><div class="v">{{ $openDays }}<span style="font-size:14px;color:var(--ink-300);font-weight:700;"> / 7</span></div><div class="d">{{ $openDays ? 'Days with at least one block' : 'Add hours so clients can book you' }}</div></div>
</div>

<details id="mw-add" class="card" style="margin-bottom:18px;padding:0;" {{ $addOpen ? 'open' : '' }}>
    <summary class="mw-card-head" style="cursor:pointer;">
        <h2>Add hours</h2>
        <span style="font-size:12px;color:var(--ink-500);">A block of time on one day, weekly or just once</span>
    </summary>
    <form method="POST" action="{{ route('therapist.availability.store') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;padding:4px 18px 18px;">
        @csrf
        <div>
            <label class="mw-label" for="day_of_week">Day</label>
            <select class="mw-field" id="day_of_week" name="day_of_week" required>
                @foreach ($days as $day)
                    <option value="{{ $day }}" {{ old('day_of_week') === $day ? 'selected' : '' }}>{{ ucfirst($day) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mw-label" for="start_time">From</label>
            <input class="mw-field" type="time" id="start_time" name="start_time" value="{{ old('start_time') }}" required>
        </div>
        <div>
            <label class="mw-label" for="end_time">To</label>
            <input class="mw-field" type="time" id="end_time" name="end_time" value="{{ old('end_time') }}" required>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-700);padding-bottom:11px;">
            <input type="checkbox" name="is_recurring" value="1" checked>
            Repeats weekly
        </label>
        <button type="submit" class="btn primary sm" style="height:41px;">Add</button>
    </form>
    <div style="padding:0 18px;">
        @error('day_of_week')<div class="mw-error" style="margin-bottom:10px;">{{ $message }}</div>@enderror
        @error('start_time')<div class="mw-error" style="margin-bottom:10px;">{{ $message }}</div>@enderror
        @error('end_time')<div class="mw-error" style="margin-bottom:10px;">{{ $message }}</div>@enderror
    </div>
</details>

<div class="card" style="padding:0;">
    <div class="mw-card-head">
        <h2>Your week</h2>
        <span style="font-size:12px;color:var(--ink-500);">6 AM – 10 PM</span>
    </div>
    <div class="mw-week">
        <div class="mw-week-row mw-week-scale" aria-hidden="true">
            <div></div>
            <div class="bar" style="background:none;">
                @foreach ([6, 9, 12, 15, 18, 21] as $h)
                    <span style="left:{{ round(($h * 60 - $dayStart) / $span * 100, 2) }}%;">{{ $h === 12 ? '12 PM' : ($h < 12 ? $h.' AM' : ($h - 12).' PM') }}</span>
                @endforeach
            </div>
            <div></div>
        </div>
        @foreach ($days as $day)
            @php $daySlots = $slots->get($day, collect()); $isToday = strtolower(now()->format('l')) === $day; @endphp
            <div class="mw-week-row">
                <div class="dn" style="{{ $isToday ? 'color:var(--mw-orange,#E8825E);' : '' }}">{{ ucfirst(substr($day, 0, 3)) }}@if ($isToday)<span style="display:block;font-size:10.5px;font-weight:700;">Today</span>@endif</div>
                <div class="bar" role="img" aria-label="{{ ucfirst($day) }}: {{ $daySlots->isEmpty() ? 'closed' : $daySlots->count().' blocks' }}">
                    @foreach ($daySlots as $slot)
                        @php
                            $a = max($dayStart, min($dayEnd, $mins($slot->start_time)));
                            $b = max($dayStart, min($dayEnd, $mins($slot->end_time)));
                        @endphp
                        @if ($b > $a)
                            <span class="blk {{ $slot->is_recurring ? '' : 'once' }}" style="left:{{ round(($a - $dayStart) / $span * 100, 2) }}%;width:{{ round(($b - $a) / $span * 100, 2) }}%;"></span>
                        @endif
                    @endforeach
                </div>
                <div class="chips">
                    @forelse ($daySlots as $slot)
                        <span class="mw-chip {{ $slot->is_recurring ? '' : 'once' }}">
                            @php $sA = $C::parse($slot->start_time); $sB = $C::parse($slot->end_time); @endphp
                            {{ $sA->format($sA->format('A') === $sB->format('A') ? 'g:i' : 'g:i A') }}–{{ $sB->format('g:i A') }}@unless ($slot->is_recurring) · once @endunless
                            <form method="POST" action="{{ route('therapist.availability.destroy', $slot) }}" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove this block" aria-label="Remove {{ ucfirst($day) }} {{ $C::parse($slot->start_time)->format('g:i A') }} block">&times;</button>
                            </form>
                        </span>
                    @empty
                        <span style="color:var(--ink-300);font-size:12.5px;">Not working</span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    Teal blocks repeat every week; striped orange blocks are one-off. Approved time off and booked sessions are taken out automatically.
</p>

<style>
    .mw-week { padding:4px 18px 14px; }
    .mw-week-row { display:grid; grid-template-columns:54px minmax(0,1fr) minmax(0,300px); gap:14px; align-items:center; padding:9px 0; }
    .mw-week-row + .mw-week-row { border-top:1px solid rgba(16,48,43,.06); }
    .mw-week-scale { padding:0 0 2px; }
    .mw-week-row .dn { font-size:13px; font-weight:800; color:var(--ink-900, #1d2b29); }
    .mw-week-row .bar { position:relative; height:14px; border-radius:7px; background:rgba(16,48,43,.06); }
    .mw-week-scale .bar { height:14px; }
    .mw-week-scale .bar span { position:absolute; top:0; transform:translateX(-50%); font-size:10.5px; color:var(--ink-300, #98a5a1); white-space:nowrap; }
    .mw-week-row .blk { position:absolute; top:0; bottom:0; border-radius:7px; background:linear-gradient(90deg, #2F6F66, #3A8C88); }
    .mw-week-row .blk.once { background:repeating-linear-gradient(45deg, #E8825E 0 6px, #F0A182 6px 12px); }
    .mw-week-row .chips { display:flex; flex-wrap:wrap; gap:6px; }
    .mw-chip { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:700; color:#24564F; background:rgba(47,111,102,.1); border-radius:999px; padding:3px 4px 3px 10px; white-space:nowrap; }
    .mw-chip.once { color:#8a4326; background:#FDF1EC; }
    .mw-chip button { width:20px; height:20px; border-radius:50%; border:0; background:none; color:inherit; opacity:.55; cursor:pointer; font-size:15px; line-height:1; }
    .mw-chip button:hover { opacity:1; background:rgba(0,0,0,.06); }
    @media (max-width: 760px) { .mw-week-row { grid-template-columns:44px minmax(0,1fr); } .mw-week-row .chips { grid-column:2; } .mw-week-scale > div:last-child { display:none; } }
</style>
@endsection
