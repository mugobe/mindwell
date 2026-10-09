@extends('layouts.app')
@section('title', 'Session reports')
@section('content')
{{-- Session reports (2026-10-08 redesign): outcome tabs, name search, avatars. Read-only. --}}
@php
    $short = [
        'schedule_next_session' => 'Next session',
        'refer_to_specialist' => 'Referred out',
        'case_closed' => 'Case closed',
        'continue_monitoring' => 'Monitoring',
        'other' => 'Other',
    ];
    $tone = ['refer_to_specialist' => 'warn', 'case_closed' => '', 'schedule_next_session' => 'success', 'continue_monitoring' => 'teal', 'other' => ''];
@endphp

<div class="mw-page-head">
    <div>
        <h1>Session reports</h1>
        <div class="sub">What each therapist recorded when they closed a session. Read-only, and never shown to clients.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.referrals.index') }}" class="btn ghost sm">Referrals</a></div>
</div>

<nav class="mw-tabs" aria-label="Filter by outcome">
    <a href="{{ route('admin.closed-sessions.index', array_filter(['q' => $q ?: null])) }}" class="{{ $outcome ? '' : 'on' }}">All <span class="n">{{ $counts->sum() }}</span></a>
    @foreach ($outcomes as $key => $label)
        <a href="{{ route('admin.closed-sessions.index', array_filter(['outcome' => $key, 'q' => $q ?: null])) }}" class="{{ $outcome === $key ? 'on' : '' }}" title="{{ $label }}">
            {{ $short[$key] ?? $label }} <span class="n">{{ $counts[$key] ?? 0 }}</span>
        </a>
    @endforeach
</nav>

<form method="GET" action="{{ route('admin.closed-sessions.index') }}" class="mw-toolbar">
    @if ($outcome)<input type="hidden" name="outcome" value="{{ $outcome }}">@endif
    <label class="mw-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Search by client or therapist" aria-label="Search reports">
    </label>
    @if ($q !== '')
        <a href="{{ route('admin.closed-sessions.index', array_filter(['outcome' => $outcome])) }}" class="btn ghost sm">Clear</a>
    @endif
    <span style="margin-left:auto;font-size:12.5px;color:var(--ink-500);">{{ $sessions->total() }} {{ $sessions->total() === 1 ? 'report' : 'reports' }}</span>
</form>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Therapist</th>
                <th>Closed</th>
                <th>Outcome</th>
                <th>Summary</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
                @php $c = $session->clientProfile?->user; @endphp
                <tr>
                    <td style="padding-left:18px;">
                        @include('partials.person', [
                            'name' => $c?->name, 'fallback' => 'Unknown client',
                            'href' => $session->clientProfile ? route('admin.clients.show', $session->clientProfile) : null,
                            'sub' => $session->session_number ? 'Session '.$session->session_number : null,
                        ])
                    </td>
                    <td>@include('partials.person', ['name' => $session->therapistProfile?->user?->name, 'fallback' => 'Unknown therapist', 'size' => 28])</td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $session->closed_at?->format('j M Y') ?? '—' }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">{{ $session->closed_at?->format('g:i A') }}</div>
                    </td>
                    <td><span class="pill {{ $tone[$session->closure_outcome] ?? '' }}" title="{{ $outcomes[$session->closure_outcome] ?? '' }}">{{ $short[$session->closure_outcome] ?? ($session->closure_outcome ?: '—') }}</span></td>
                    <td style="font-size:12.5px;color:var(--ink-500);max-width:300px;">
                        {{ \Illuminate\Support\Str::limit($session->session_summary ?: $session->closure_report ?: '—', 80) }}
                    </td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        <a href="{{ route('admin.closed-sessions.show', $session) }}" class="btn ghost sm">Open</a>
                        <a href="{{ route('admin.closed-sessions.pdf', $session) }}" class="btn ghost sm" title="Download PDF" aria-label="Download PDF">PDF</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h4"/></svg>
                            <div class="big">{{ $q !== '' || $outcome ? 'No reports match' : 'No reports yet' }}</div>
                            {{ $q !== '' || $outcome ? 'Try another name or outcome.' : 'Reports appear here once therapists close out sessions.' }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $sessions->links() }}</div>
@endsection
