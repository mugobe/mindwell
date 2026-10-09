@extends('layouts.app')
@section('title', 'Session Report')
@section('content')

{{-- 2026-10-08 redesign (round 3): page head with the client's bubble. --}}
@php $mwC = $session->clientProfile?->user; @endphp
<div class="mw-page-head">
    <div style="display:flex;align-items:center;gap:14px;">
        @include('partials.avatar', ['name' => $mwC?->name ?? 'Client', 'size' => 46])
        <div>
            <h1>{{ $mwC?->name ?? 'Unknown client' }}{{ $session->session_number ? ' · session '.$session->session_number : '' }}</h1>
            <div class="sub">Session report · {{ $session->scheduled_at?->format('l, j M Y') ?? '—' }}{{ $session->isInPerson() ? ' · in person' : ' · video' }}</div>
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('therapist.reports.index') }}" class="btn ghost sm">All notes</a>
        @if ($session->client_profile_id)<a href="{{ route('therapist.clients.show', $session->client_profile_id) }}" class="btn ghost sm">Client file</a>@endif
        <a href="{{ route('therapist.reports.pdf', $session) }}" class="btn primary sm">Download PDF</a>
    </div>
</div>

<div class="card" style="max-width:720px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;font-size:13px;">
        <div>
            <div class="mw-label">Session was</div>
            <div style="color:var(--ink-900);">{{ $session->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</div>
        </div>
        <div>
            <div class="mw-label">Closed out</div>
            <div style="color:var(--ink-900);">{{ $session->closed_at?->format('M j, Y g:i A') ?? '—' }}</div>
            @if ($session->session_number)
                <div style="font-size:11.5px;color:var(--ink-500);margin-top:2px;">Session #{{ $session->session_number }} for this client</div>
            @endif
            @if ($session->isInPerson())
                <div style="font-size:11.5px;color:var(--ink-500);margin-top:2px;">In person{{ $session->locationText() ? ' · '.$session->locationText() : '' }}</div>
            @endif
        </div>
    </div>

    <div class="mw-label">Outcome</div>
    <span class="pill success" style="margin-bottom:8px;display:inline-block;">
        {{ $outcomes[$session->closure_outcome] ?? $session->closure_outcome }}
    </span>

    @if ($session->previous_session_id)
        <p style="font-size:11px;color:var(--ink-300);margin:0 0 18px;">
            This session was scheduled as a follow-up from a previous closure.
            @if ($session->follow_up_notes)
                Notes at scheduling time: "{{ $session->follow_up_notes }}"
            @endif
        </p>
    @else
        <div style="margin-bottom:18px;"></div>
    @endif

    @if ($session->session_summary || $session->presenting_concerns || $session->progress_notes || $session->risk_assessment || $session->plan_notes)
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <div class="mw-label">Presenting concerns</div>
                <p style="font-size:13.5px;color:var(--ink-900);white-space:pre-wrap;line-height:1.6;margin-top:4px;">{{ $session->presenting_concerns ?: '—' }}</p>
            </div>
            <div>
                <div class="mw-label">Session summary</div>
                <p style="font-size:13.5px;color:var(--ink-900);white-space:pre-wrap;line-height:1.6;margin-top:4px;">{{ $session->session_summary ?: '—' }}</p>
            </div>
            <div>
                <div class="mw-label">Progress since last visit</div>
                <p style="font-size:13.5px;color:var(--ink-900);white-space:pre-wrap;line-height:1.6;margin-top:4px;">{{ $session->progress_notes ?: '—' }}</p>
            </div>
            <div>
                <div class="mw-label">Risk assessment</div>
                <p style="font-size:13.5px;color:var(--ink-900);white-space:pre-wrap;line-height:1.6;margin-top:4px;">{{ $session->risk_assessment ?: '—' }}</p>
            </div>
            <div>
                <div class="mw-label">Plan / next steps</div>
                <p style="font-size:13.5px;color:var(--ink-900);white-space:pre-wrap;line-height:1.6;margin-top:4px;">{{ $session->plan_notes ?: '—' }}</p>
            </div>
            @foreach (['interventions_used' => 'Interventions used', 'homework' => 'Homework / activities given', 'goals_progress' => 'Progress toward goals'] as $mwField => $mwLabel)
                @if ($session->{$mwField})
                    <div>
                        <div class="mw-label">{{ $mwLabel }}</div>
                        <p style="font-size:13.5px;color:var(--ink-900);white-space:pre-wrap;line-height:1.6;margin-top:4px;">{{ $session->{$mwField} }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <div class="mw-label">Report</div>
        <p style="font-size:13.5px;color:var(--ink-900);white-space:pre-wrap;line-height:1.6;margin-top:6px;">{{ $session->closure_report ?: '—' }}</p>
        <p style="font-size:11px;color:var(--ink-300);margin-top:10px;">Closed before the structured report format was added.</p>
    @endif
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:14px;max-width:720px;">
    This is exactly what you submitted when you closed the session out — it isn't shown to the client anywhere
    in the portal.
</p>
@endsection
