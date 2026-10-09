@extends('layouts.app')
@section('title', 'My stats')
@section('content')
{{-- Added 2026-10-06 (roadmap 79) — see App\Services\TherapistOverview for definitions.
     2026-10-08 redesign (round 3): page kit tiles in two groups, cleaner monthly chart with
     hover values and a screen-reader table. --}}
@php
    $groups = [
        'Your caseload' => [
            ['Active clients', $counts['active_clients'], 'Upcoming session or seen in the last 60 days', route('therapist.clients.index'), null],
            ['Upcoming sessions', $counts['upcoming'], $counts['pending_approval'].' more awaiting admin approval', route('therapist.sessions.index'), null],
            ['Follow-ups', $counts['follow_ups'], 'Upcoming sessions you scheduled', route('therapist.sessions.index'), null],
            ['Check-ins due', $counts['check_ins_due'], 'Active clients due a 2-weekly check-in', route('therapist.clients.index'), null],
        ],
        'Your work' => [
            ['Sessions completed', $counts['completed_total'], $counts['completed_30'].' in the last 30 days', route('therapist.reports.index'), null],
            ['To close out', $counts['to_close'], 'Finished, report not written yet', route('therapist.sessions.index'), $counts['to_close'] ? 'var(--mw-orange, #E8825E)' : null],
            ['Missed', $counts['missed'], 'Client never joined the video room', route('therapist.sessions.index'), $counts['missed'] ? '#C0392B' : null],
            ['Referrals', $counts['referrals'], 'Sessions closed with "Refer out"', route('therapist.reports.index', ['outcome' => 'refer_to_specialist']), null],
        ],
    ];
    $max = max(1, collect($monthly)->max('count'));
    $peak = collect($monthly)->max('count');
    $total6 = collect($monthly)->sum('count');
@endphp

<div class="mw-page-head">
    <div>
        <h1>My stats</h1>
        <div class="sub">
            @if ($counts['rating'])
                <span style="color:#e8a33d;">★</span> <strong style="color:var(--ink-900);">{{ number_format($counts['rating']['average'], 1) }} / 5</strong> average from {{ $counts['rating']['count'] }} client ratings ·
            @endif
            Numbers update as sessions are booked and closed.
        </div>
    </div>
    <div class="actions"><a href="{{ route('therapist.reports.index') }}" class="btn ghost sm">Session notes</a></div>
</div>

@foreach ($groups as $gTitle => $tiles)
    <div style="font-size:12px;font-weight:800;color:var(--ink-500);text-transform:uppercase;letter-spacing:.05em;margin:4px 0 8px;">{{ $gTitle }}</div>
    <div class="mw-kpis">
        @foreach ($tiles as [$label, $value, $hint, $link, $col])
            <a class="mw-kpi" href="{{ $link }}">
                <div class="k">{{ $label }}</div>
                <div class="v" @if ($col) style="color:{{ $col }};" @endif>{{ $value }}</div>
                <div class="d">{{ $hint }}</div>
            </a>
        @endforeach
    </div>
@endforeach

<div class="card" style="padding:0;">
    <div class="mw-card-head">
        <h2>Sessions completed per month</h2>
        <span style="font-size:12px;color:var(--ink-500);">{{ $total6 }} in the last 6 months</span>
    </div>
    <div style="padding:8px 22px 18px;">
        <div class="mw-bars" role="img" aria-label="Sessions completed per month for the last six months">
            @foreach ($monthly as $m)
                @php $isNow = $loop->last; $showLabel = $m['count'] > 0 && ($isNow || $m['count'] === $peak); @endphp
                <div class="col" tabindex="0">
                    <span class="tip">{{ $m['label'] }}: {{ $m['count'] }} {{ $m['count'] === 1 ? 'session' : 'sessions' }}</span>
                    @if ($showLabel)<span class="val">{{ $m['count'] }}</span>@endif
                    <span class="bar" style="height:{{ $m['count'] > 0 ? max(3, round($m['count'] / $max * 140, 1)) : 0 }}px;"></span>
                </div>
            @endforeach
        </div>
        <div class="mw-bars-x">
            @foreach ($monthly as $m)<span style="{{ $loop->last ? 'color:var(--ink-900);font-weight:700;' : '' }}">{{ $m['label'] }}{{ $loop->last ? ' (so far)' : '' }}</span>@endforeach
        </div>
        <table style="position:absolute;left:-9999px;">
            <caption>Sessions completed per month</caption>
            <tr><th>Month</th><th>Sessions</th></tr>
            @foreach ($monthly as $m)<tr><td>{{ $m['label'] }}</td><td>{{ $m['count'] }}</td></tr>@endforeach
        </table>
    </div>
</div>

<style>
    .mw-bars { display:flex; align-items:flex-end; gap:2px; height:170px; border-bottom:1px solid rgba(16,48,43,.14); background:linear-gradient(rgba(16,48,43,.05) 1px, transparent 1px) 0 0 / 100% 50%; }
    .mw-bars .col { position:relative; flex:1; height:100%; display:flex; flex-direction:column; justify-content:flex-end; align-items:center; outline:none; }
    .mw-bars .col:hover, .mw-bars .col:focus { background:rgba(47,111,102,.04); }
    .mw-bars .bar { width:100%; max-width:46px; background:#2F6F66; border-radius:4px 4px 0 0; }
    .mw-bars .val { font-size:11.5px; font-weight:700; color:var(--ink-700, #3a4a47); margin-bottom:4px; font-variant-numeric:tabular-nums; }
    .mw-bars .tip { position:absolute; bottom:calc(100% - 6px); left:50%; transform:translateX(-50%); background:#1d2b29; color:#fff; font-size:11.5px; font-weight:600; padding:5px 8px; border-radius:7px; white-space:nowrap; opacity:0; pointer-events:none; transition:opacity .12s; z-index:2; }
    .mw-bars .col:hover .tip, .mw-bars .col:focus .tip { opacity:1; }
    .mw-bars-x { display:flex; gap:2px; padding-top:7px; }
    .mw-bars-x span { flex:1; text-align:center; font-size:11.5px; color:var(--ink-500, #5f6f6b); }
</style>
@endsection
