@extends('layouts.app')
@section('title', 'Book a Session')
@section('content')
{{-- Booking with one therapist. 2026-11-07 (Collins: "the list is endless, why not have
     their calendar instead"): a month calendar with the open days marked, the chosen
     day's times grouped into morning / afternoon / evening, online or in person, and one
     Continue button. Looks booking_days_ahead days ahead (default 28).
     A client without their first assessment can still look, but Continue takes them to
     the assessment first and brings them back to the same time ($picked / $pickedMode). --}}
@php
    $mwInPerson = (bool) $therapist->offers_in_person;
    $mwCur = config('flutterwave.currency');
    $mwVideoPrice = $mwCur.' '.number_format((float) config('mindwell.one_off_session_price'), 0);
    $mwInPersonPrice = $mwCur.' '.number_format((float) config('mindwell.in_person_session_price'), 0);
    $mwMinutes = (int) config('mindwell.session_duration_minutes', 70);

    $mwByDay = $slots->groupBy(fn ($s) => $s->toDateString());
    $mwSelDay = $picked ? substr($picked, 0, 10) : $mwByDay->keys()->first();
    $mwMonths = [];
    for ($m = now()->startOfMonth(); $m->lte(now()->addDays($daysAhead)->startOfMonth()); $m->addMonth()) {
        $mwMonths[] = $m->copy();
    }
    $mwSelMonth = 0;
    foreach ($mwMonths as $i => $m) {
        if ($mwSelDay && substr($mwSelDay, 0, 7) === $m->format('Y-m')) { $mwSelMonth = $i; }
    }
    $mwGroups = ['Morning' => [0, 12], 'Afternoon' => [12, 17], 'Evening' => [17, 24]];
@endphp

<style>
    .mwb-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
    .mwb-head h1 { font-size:26px; font-weight:800; letter-spacing:-.02em; color:var(--ink-900); margin:0; }
    .mwb-who { display:flex; gap:16px; align-items:flex-start; margin-bottom:16px; }
    .mwb-wrap { display:grid; grid-template-columns:340px minmax(0,1fr); gap:16px; align-items:start; }
    @media (max-width: 860px) { .mwb-wrap { grid-template-columns:1fr; } }
    .mwb-wrap .card { margin:0; min-width:0; }
    .mwb-h2 { font-size:16px; font-weight:800; color:var(--ink-900); margin:0 0 12px; letter-spacing:-.01em; }

    .mwb-month-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
    .mwb-month-head strong { font-size:15px; font-weight:800; color:var(--ink-900); }
    .mwb-nav { width:36px; height:36px; border-radius:50%; border:0; background:#F7F9FC; color:var(--mw-navy, #034C8F); font-size:18px; font-weight:800; cursor:pointer; display:grid; place-items:center; }
    .mwb-nav:hover:not(:disabled) { background:var(--mw-navy-tint, #E6EFF9); }
    .mwb-nav:disabled { opacity:.35; cursor:default; }
    .mwb-wd, .mwb-grid { display:grid; grid-template-columns:repeat(7, 1fr); gap:4px; text-align:center; }
    .mwb-wd span { font-size:11px; font-weight:700; color:var(--ink-500); text-transform:uppercase; letter-spacing:.04em; padding:4px 0; }
    .mwb-day { position:relative; aspect-ratio:1; max-width:100%; border-radius:50%; display:grid; place-items:center; font-size:14px; font-weight:600; color:#A3AFC0; border:0; background:none; font-family:inherit; padding:0; }
    .mwb-day.open { color:var(--ink-900); font-weight:800; background:var(--mw-navy-tint, #E6EFF9); cursor:pointer; transition:background .15s, transform .12s; }
    .mwb-day.open:hover { background:#D3E3F6; }
    .mwb-day.open:active { transform:scale(.94); }
    .mwb-day.open i { position:absolute; bottom:5px; left:50%; width:4px; height:4px; margin-left:-2px; border-radius:50%; background:var(--mw-green-500, #00B888); }
    .mwb-day.open[aria-pressed="true"] { background:var(--mw-navy, #034C8F); color:#fff; }
    .mwb-day.open[aria-pressed="true"] i { background:#fff; }
    .mwb-day.today:not(.open) { color:var(--ink-700); box-shadow:inset 0 0 0 1.5px #D5DCE6; }
    .mwb-key { display:flex; gap:14px; flex-wrap:wrap; font-size:12px; color:var(--ink-500); margin-top:12px; }
    .mwb-key span { display:inline-flex; align-items:center; gap:6px; }
    .mwb-key b { width:14px; height:14px; border-radius:50%; background:var(--mw-navy-tint, #E6EFF9); display:inline-block; }

    .mwb-dayhead { display:flex; justify-content:space-between; align-items:baseline; gap:10px; margin-bottom:12px; }
    .mwb-dayhead h2 { margin:0; }
    .mwb-dayhead small { font-size:12.5px; color:var(--ink-500); font-weight:600; }
    .mwb-gl { font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--mw-green, #00A87C); margin:12px 0 8px; }
    .mwb-gl:first-of-type { margin-top:0; }
    .mwb-chips { display:grid; grid-template-columns:repeat(auto-fill, minmax(96px, 1fr)); gap:8px; }
    .mwb-chip { position:relative; }
    .mwb-chip input { position:absolute; opacity:0; inset:0; margin:0; cursor:pointer; }
    .mwb-chip span { display:block; text-align:center; padding:10px 6px; border-radius:999px; border:1.5px solid #D5DCE6; background:#fff; font-size:13.5px; font-weight:700; color:var(--ink-700); transition:all .15s; font-variant-numeric:tabular-nums; }
    .mwb-chip input:hover + span { border-color:var(--mw-navy, #034C8F); }
    .mwb-chip input:checked + span { background:var(--mw-navy, #034C8F); border-color:var(--mw-navy, #034C8F); color:#fff; }
    .mwb-chip input:focus-visible + span { outline:2.5px solid var(--mw-navy, #034C8F); outline-offset:2px; }

    .mwb-modes { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:4px; }
    @media (max-width: 480px) { .mwb-modes { grid-template-columns:1fr; } }
    .mwb-mode { position:relative; }
    .mwb-mode input { position:absolute; opacity:0; inset:0; margin:0; cursor:pointer; }
    .mwb-mode span { display:block; padding:12px 14px; border-radius:14px; border:1.5px solid #D5DCE6; background:#fff; transition:all .15s; }
    .mwb-mode b { display:block; font-size:14px; color:var(--ink-900); }
    .mwb-mode em { font-style:normal; font-size:12.5px; color:var(--ink-500); }
    .mwb-mode input:checked + span { border-color:var(--mw-navy, #034C8F); background:var(--mw-navy-tint, #E6EFF9); }
    .mwb-mode input:focus-visible + span { outline:2.5px solid var(--mw-navy, #034C8F); outline-offset:2px; }

    .mwb-confirm { border-top:1px solid rgba(3,40,80,.08); margin-top:18px; padding-top:16px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
    .mwb-sum { font-size:14px; color:var(--ink-700); min-width:0; }
    .mwb-sum strong { color:var(--ink-900); display:block; font-size:15px; }
    .mwb-confirm .btn { border-radius:999px; padding:12px 26px; font-size:14.5px; }
    .mwb-confirm .btn:disabled { opacity:.45; cursor:not-allowed; }
    .mwb-note { font-size:12px; color:var(--ink-500); margin:8px 0 0; width:100%; }
</style>

<div class="mwb-head">
    <h1>Book with {{ $therapist->user->name }}</h1>
    <a href="{{ route('therapists.browse') }}" class="btn ghost sm" style="border-radius:999px;">Back to all therapists</a>
</div>

@if ($needsAssessment)
    <div class="mw-note info top">
        <div class="mw-note-body">
            <div class="mw-note-title">One step before you book</div>
            <div class="mw-note-text">Your first booking starts with a short assessment, about 5 minutes. {{ $therapist->user->name }} reads it before you meet. Pick a time below and we'll take you through it, then bring you back to confirm.</div>
        </div>
        <div class="mw-note-act"><a class="mw-pill-btn" href="{{ route('intake.show', ['book' => $therapist->id]) }}">Start it now</a></div>
    </div>
@endif

{{-- Therapist profile (2026-10-06): what they wrote in My profile. --}}
@if ($therapist->bio || $therapist->qualifications || $therapist->professional_title || $therapist->photo_path)
    <div class="card mwb-who">
        @include('partials.therapist-avatar', ['therapist' => $therapist, 'size' => 72])
        <div style="flex:1;min-width:0;">
            <div style="font-size:16px;font-weight:800;color:var(--ink-900);">{{ $therapist->user->name }}</div>
            <div style="font-size:12.5px;color:var(--ink-700);">{{ collect([$therapist->professional_title, $therapist->languages ? 'Speaks '.$therapist->languages : null])->filter()->join(' · ') }}</div>
            @if ($therapist->bio)
                <p style="font-size:13px;color:var(--ink-700);line-height:1.6;margin:8px 0 0;white-space:pre-wrap;">{{ $therapist->bio }}</p>
            @endif
            @if ($therapist->qualifications)
                <div style="font-size:12px;color:var(--ink-500);margin-top:8px;"><strong>Qualifications:</strong> {{ $therapist->qualifications }}</div>
            @endif
            @if ($therapist->specialties)
                <div style="font-size:12px;color:var(--ink-500);margin-top:4px;"><strong>Specialities:</strong> {{ is_array($therapist->specialties) ? implode(', ', $therapist->specialties) : $therapist->specialties }}</div>
            @endif
        </div>
    </div>
@endif

@if ($slots->isEmpty())
    <div class="card" style="text-align:center;padding:36px 24px;">
        @include('partials.mw-illustration', ['variant' => 'calendar', 'size' => 104])
        <p style="color:var(--ink-500);font-size:13px;margin-top:12px;">
            No open times in the next {{ $daysAhead }} days. Please check back soon, or
            <a href="{{ route('therapists.browse') }}" style="color:var(--teal-600);">choose another therapist</a>.
        </p>
    </div>
@else
<form method="POST" action="{{ route('therapists.book.store', $therapist) }}" class="mwb-wrap" id="mwb-form">
    @csrf

    {{-- ===== Calendar ===== --}}
    <section class="card" aria-labelledby="mwb-cal-h">
        <h2 class="mwb-h2" id="mwb-cal-h">Pick a day</h2>
        @foreach ($mwMonths as $mi => $month)
            <div class="mwb-month" data-month="{{ $mi }}" @if ($mi !== $mwSelMonth) hidden @endif>
                <div class="mwb-month-head">
                    <button type="button" class="mwb-nav" data-go="{{ $mi - 1 }}" @disabled($mi === 0) aria-label="Previous month">&lsaquo;</button>
                    <strong>{{ $month->format('F Y') }}</strong>
                    <button type="button" class="mwb-nav" data-go="{{ $mi + 1 }}" @disabled($mi === count($mwMonths) - 1) aria-label="Next month">&rsaquo;</button>
                </div>
                <div class="mwb-wd" aria-hidden="true">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd)<span>{{ $wd }}</span>@endforeach
                </div>
                <div class="mwb-grid">
                    @for ($d = $month->copy()->startOfWeek(\Carbon\Carbon::MONDAY); $d->lte($month->copy()->endOfMonth()); $d->addDay())
                        @php($key = $d->toDateString())
                        @if ($d->month !== $month->month)
                            <span class="mwb-day" aria-hidden="true"></span>
                        @elseif ($mwByDay->has($key))
                            <button type="button" class="mwb-day open {{ $d->isToday() ? 'today' : '' }}" data-day="{{ $key }}"
                                    aria-pressed="{{ $key === $mwSelDay ? 'true' : 'false' }}"
                                    aria-label="{{ $d->format('l j F') }}, {{ $mwByDay[$key]->count() }} {{ \Illuminate\Support\Str::plural('time', $mwByDay[$key]->count()) }} free">{{ $d->day }}<i></i></button>
                        @else
                            <span class="mwb-day {{ $d->isToday() ? 'today' : '' }}" aria-hidden="true">{{ $d->day }}</span>
                        @endif
                    @endfor
                </div>
            </div>
        @endforeach
        <div class="mwb-key"><span><b></b> Has free times</span><span>Showing the next {{ $daysAhead }} days</span></div>
    </section>

    {{-- ===== Times for the chosen day, kind of session, confirm ===== --}}
    <section class="card" aria-live="polite">
        @foreach ($mwByDay as $day => $times)
            <div class="mwb-times" data-day="{{ $day }}" @if ($day !== $mwSelDay) hidden @endif>
                <div class="mwb-dayhead">
                    <h2 class="mwb-h2">{{ $times->first()->format('l, j F') }}</h2>
                    <small>{{ $times->count() }} {{ \Illuminate\Support\Str::plural('time', $times->count()) }} free · {{ $mwMinutes }} min each</small>
                </div>
                @foreach ($mwGroups as $label => [$from, $to])
                    @php($inGroup = $times->filter(fn ($t) => $t->hour >= $from && $t->hour < $to))
                    @if ($inGroup->isNotEmpty())
                        <div class="mwb-gl">{{ $label }}</div>
                        <div class="mwb-chips">
                            @foreach ($inGroup as $t)
                                <label class="mwb-chip">
                                    <input type="radio" name="scheduled_at" value="{{ $t->toDateTimeString() }}" required
                                           data-label="{{ $t->format('l j F') }} at {{ $t->format('g:i A') }}"
                                           @checked($picked === $t->toDateTimeString())>
                                    <span>{{ $t->format('g:i A') }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach

        <div style="margin-top:18px;">
            @if ($mwInPerson)
                <div class="mwb-gl" style="margin-top:0;">How would you like to meet?</div>
                <div class="mwb-modes">
                    <label class="mwb-mode">
                        <input type="radio" name="session_mode" value="video" data-label="Online" data-price="{{ $mwVideoPrice }}" @checked($pickedMode === 'video')>
                        <span><b>Online, by video</b><em>{{ $mwVideoPrice }}</em></span>
                    </label>
                    <label class="mwb-mode">
                        <input type="radio" name="session_mode" value="in_person" data-label="In person" data-price="{{ $mwInPersonPrice }}" @checked($pickedMode === 'in_person')>
                        <span><b>In person</b><em>{{ $mwInPersonPrice }} · our team confirms the place</em></span>
                    </label>
                </div>
            @else
                <input type="hidden" name="session_mode" value="video">
                <div style="font-size:13px;color:var(--ink-500);">Online, by video · {{ $mwVideoPrice }}</div>
            @endif
        </div>

        <div class="mwb-confirm">
            <div class="mwb-sum" id="mwb-sum"><strong>Choose a time</strong>Pick a day on the calendar, then a time.</div>
            <button type="submit" class="btn primary" id="mwb-go" @disabled(! $picked)>{{ $needsAssessment ? 'Continue to the assessment' : 'Continue' }}</button>
            <p class="mwb-note">
                @if ($needsAssessment)
                    After the assessment we'll bring you back here to confirm this time.
                @else
                    A plan or employer credit covers it. Otherwise you pay on the next step. Our team confirms each booking.
                @endif
            </p>
        </div>
    </section>
</form>

<script>
(function () {
    var form = document.getElementById('mwb-form');
    if (!form) return;
    var months = form.querySelectorAll('.mwb-month');
    var minutes = {{ $mwMinutes }};
    var single = @json($mwVideoPrice);

    function showMonth(i) { months.forEach(function (m) { m.hidden = m.dataset.month !== String(i); }); }
    form.querySelectorAll('.mwb-nav').forEach(function (b) {
        b.addEventListener('click', function () { showMonth(b.dataset.go); });
    });

    function selectDay(day) {
        form.querySelectorAll('.mwb-day.open').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.day === day ? 'true' : 'false'); });
        form.querySelectorAll('.mwb-times').forEach(function (p) {
            var on = p.dataset.day === day;
            p.hidden = !on;
            if (!on) p.querySelectorAll('input:checked').forEach(function (r) { r.checked = false; });
        });
        summary();
    }
    form.querySelectorAll('.mwb-day.open').forEach(function (b) {
        b.addEventListener('click', function () { selectDay(b.dataset.day); });
    });

    function summary() {
        var time = form.querySelector('input[name="scheduled_at"]:checked');
        var mode = form.querySelector('input[name="session_mode"]:checked');
        var sum = document.getElementById('mwb-sum');
        document.getElementById('mwb-go').disabled = !time;
        if (!time) { sum.innerHTML = '<strong>Choose a time</strong>Pick a day on the calendar, then a time.'; return; }
        var strong = document.createElement('strong'); strong.textContent = time.dataset.label;
        sum.innerHTML = ''; sum.appendChild(strong);
        sum.appendChild(document.createTextNode((mode ? mode.dataset.label + ' · ' + mode.dataset.price : 'Online · ' + single) + ' · ' + minutes + ' min'));
    }
    form.addEventListener('change', summary);
    summary();
})();
</script>
@endif
@endsection
