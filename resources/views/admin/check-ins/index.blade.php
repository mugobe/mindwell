@extends('layouts.app')
@section('title', 'Check-ins')
@section('content')
{{-- Added 2026-10-06 (roadmap 28) — see Admin\CheckInOverviewController. 2026-10-08 redesign: tabs, search, status dots. --}}
@php
    $S = \App\Support\Screeners::class;
    $labels = [
        'overdue' => ['danger', 'Overdue', 'Due more than '.config('mindwell.check_in_reminder_delay_days', 2).' days ago — they have been sent a reminder'],
        'due' => ['warn', 'Due', 'Due now — shown on their dashboard'],
        'up_to_date' => ['success', 'Up to date', 'Checked in within the last 2 weeks'],
        'no_intake' => ['', 'No intake', 'In care but never did the first assessment'],
    ];
    $dot = ['overdue' => 'bad', 'due' => 'wait', 'up_to_date' => 'ok', 'no_intake' => ''];
    // 2026-10-08 redesign: name search runs over the rows already loaded.
    $q = trim((string) request('q', ''));
    if ($q !== '') {
        $needle = mb_strtolower($q);
        $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['profile']->user->name.' '.($r['therapist'] ?? '').' '.$r['profile']->user->email), $needle))->values();
    }
@endphp

<div class="mw-page-head">
    <div>
        <h1>Check-ins</h1>
        <div class="sub">Every {{ config('mindwell.check_in_interval_days', 14) }} days each client in care repeats the PHQ-9 and GAD-7. Overdue clients are reminded by email and SMS automatically.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.clients.index') }}" class="btn ghost sm">All clients</a></div>
</div>

<nav class="mw-tabs" aria-label="Filter by status">
    <a href="{{ route('admin.check-ins.index', array_filter(['q' => $q ?: null])) }}" class="{{ $filter ? '' : 'on' }}">All in care <span class="n">{{ $total }}</span></a>
    @foreach ($labels as $key => [$pill, $label, $hint])
        <a href="{{ route('admin.check-ins.index', array_filter(['status' => $key, 'q' => $q ?: null])) }}" class="{{ $filter === $key ? 'on' : '' }}" title="{{ $hint }}">{{ $label }} <span class="n" @if ($key === 'overdue' && ($counts[$key] ?? 0) > 0) style="background:#C0392B;color:#fff;" @endif>{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>

<form method="GET" action="{{ route('admin.check-ins.index') }}" class="mw-toolbar">
    @if ($filter)<input type="hidden" name="status" value="{{ $filter }}">@endif
    <label class="mw-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Search by client or therapist" aria-label="Search check-ins">
    </label>
    @if ($q !== '')
        <a href="{{ route('admin.check-ins.index', array_filter(['status' => $filter])) }}" class="btn ghost sm">Clear</a>
    @endif
    <span style="margin-left:auto;font-size:12.5px;color:var(--ink-500);">{{ $filter ? $labels[$filter][2] : $rows->count().' '.($rows->count() === 1 ? 'client' : 'clients') }}</span>
</form>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Therapist</th>
                <th>Last assessed</th>
                <th>Next due</th>
                <th>Latest scores</th>
                <th>Check-ins</th>
                <th>Status</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                @php [$pill, $label] = $labels[$r['key']]; @endphp
                <tr>
                    <td style="padding-left:18px;">
                        @include('partials.person', ['name' => $r['profile']->user->name, 'href' => route('admin.clients.show', $r['profile']), 'sub' => $r['profile']->user->clientLabel()])
                    </td>
                    <td style="font-size:12.5px;">@if ($r['therapist']) @include('partials.person', ['name' => $r['therapist'], 'size' => 28]) @else — @endif</td>
                    <td style="font-size:12.5px;white-space:nowrap;">{{ $r['status']['last_at']?->format('j M Y') ?? '—' }}</td>
                    <td style="font-size:12.5px;white-space:nowrap;">
                        {{ $r['status']['due_at']?->format('j M') ?? '—' }}
                        @if ($r['status']['overdue_days'] > 0)
                            <div style="font-size:11px;color:var(--danger, #c0392b);">{{ $r['status']['overdue_days'] }} day(s) late</div>
                        @endif
                    </td>
                    <td style="font-size:12px;white-space:nowrap;">
                        @if ($r['latest'])
                            <div><span style="color:var(--ink-500);">PHQ-9</span> <strong>{{ $r['latest']['phq9'] }}</strong> <span style="color:var(--ink-300);">·</span> <span style="color:var(--ink-500);">GAD-7</span> <strong>{{ $r['latest']['gad7'] }}</strong></div>
                            <div style="font-size:11px;color:var(--ink-300);">{{ $S::band('phq9', $r['latest']['phq9']) }} / {{ $S::band('gad7', $r['latest']['gad7']) }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $r['checkIns'] }}</td>
                    <td style="white-space:nowrap;"><span class="mw-dot {{ $dot[$r['key']] ?? '' }}">{{ $label }}</span></td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        <a href="{{ route('admin.clients.progress', $r['profile']) }}" class="btn ghost sm">Progress</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2.5-6 4 12 2.5-6H21"/></svg>
                            <div class="big">{{ $filter || $q !== '' ? 'No one here' : 'No clients in care yet' }}</div>
                            {{ $filter === 'overdue' ? 'Nobody is late with a check-in.' : ($filter || $q !== '' ? 'Try another group or name.' : 'Clients appear here once they have had a session.') }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
