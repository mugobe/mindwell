@extends('layouts.app')
@section('title', 'Session notes')
@section('content')
{{-- Therapist → Session notes (2026-10-08 redesign, round 3): outcome tabs, name search, avatars.
     Uses the same ?outcome= and ?client= filters as before (Therapist\ReportController::index). --}}
@php
    $short = [
        'schedule_next_session' => ['success', 'Next session'],
        'refer_to_specialist' => ['warn', 'Referred out'],
        'case_closed' => ['', 'Case closed'],
        'continue_monitoring' => ['teal', 'Monitoring'],
        'other' => ['', 'Other'],
    ];
    $outcome = array_key_exists((string) request('outcome'), $outcomes) ? (string) request('outcome') : null;
    $client = trim((string) request('client', ''));
@endphp

<div class="mw-page-head">
    <div>
        <h1>Session notes</h1>
        <div class="sub">Every report you've written when closing a session. Only you and the Mindwell team can see them.</div>
    </div>
    <div class="actions"><a href="{{ route('therapist.sessions.index') }}" class="btn ghost sm">Sessions</a></div>
</div>

<nav class="mw-tabs" aria-label="Filter by outcome">
    <a href="{{ route('therapist.reports.index', array_filter(['client' => $client ?: null])) }}" class="{{ $outcome ? '' : 'on' }}">All</a>
    @foreach ($outcomes as $key => $label)
        <a href="{{ route('therapist.reports.index', array_filter(['outcome' => $key, 'client' => $client ?: null])) }}" class="{{ $outcome === $key ? 'on' : '' }}" title="{{ $label }}">{{ $short[$key][1] ?? $label }}</a>
    @endforeach
</nav>

<form method="GET" action="{{ route('therapist.reports.index') }}" class="mw-toolbar">
    @if ($outcome)<input type="hidden" name="outcome" value="{{ $outcome }}">@endif
    <label class="mw-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="client" value="{{ $client }}" placeholder="Search by client name" aria-label="Search by client name">
    </label>
    @if ($client !== '')
        <a href="{{ route('therapist.reports.index', array_filter(['outcome' => $outcome])) }}" class="btn ghost sm">Clear</a>
    @endif
    <span style="margin-left:auto;font-size:12.5px;color:var(--ink-500);">{{ $reports->total() }} {{ $reports->total() === 1 ? 'report' : 'reports' }}</span>
</form>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Closed</th>
                <th>Outcome</th>
                <th>Summary</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reports as $session)
                @php [$oc, $ol] = $short[$session->closure_outcome] ?? ['', $session->closure_outcome ?: '—']; @endphp
                <tr>
                    <td style="padding-left:18px;">@include('partials.person', ['name' => $session->clientProfile?->user?->name, 'fallback' => 'Unknown client', 'href' => route('therapist.clients.show', $session->client_profile_id), 'sub' => $session->session_number ? 'Session '.$session->session_number : null])</td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $session->closed_at?->format('j M Y') ?? '—' }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">{{ $session->closed_at?->format('g:i A') }}</div>
                    </td>
                    <td><span class="pill {{ $oc }}" title="{{ $outcomes[$session->closure_outcome] ?? '' }}">{{ $ol }}</span></td>
                    <td style="font-size:12.5px;color:var(--ink-500);max-width:300px;">{{ \Illuminate\Support\Str::limit($session->session_summary ?: $session->closure_report ?: '—', 80) }}</td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        <a href="{{ route('therapist.reports.show', $session) }}" class="btn ghost sm">Open</a>
                        <a href="{{ route('therapist.reports.pdf', $session) }}" class="btn ghost sm" aria-label="Download PDF">PDF</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h4"/></svg>
                            <div class="big">{{ $outcome || $client !== '' ? 'No reports match' : 'No reports yet' }}</div>
                            {{ $outcome || $client !== '' ? 'Try another name or outcome.' : 'Sessions you close out will show up here.' }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $reports->links() }}</div>
@endsection
