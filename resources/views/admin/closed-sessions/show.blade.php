@extends('layouts.app')
@section('title', 'Session Report')
@section('content')

<div class="dash-head">
    <h1>Session report</h1>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.closed-sessions.pdf', $session) }}" class="btn primary sm">Download PDF</a>
        <a href="{{ route('admin.closed-sessions.index') }}" class="btn ghost sm">Back to all reports</a>
    </div>
</div>

<div class="card" style="max-width:640px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;font-size:13px;">
        <div>
            <div class="mw-label">Client</div>
            <div style="color:var(--ink-900);font-weight:600;">{{ $session->clientProfile?->user?->name ?? 'Unknown client' }}</div>
        </div>
        <div>
            <div class="mw-label">Therapist</div>
            <div style="color:var(--ink-900);font-weight:600;">{{ $session->therapistProfile?->user?->name ?? 'Unknown therapist' }}</div>
        </div>
        <div>
            <div class="mw-label">Session was</div>
            <div style="color:var(--ink-900);">{{ $session->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</div>
        </div>
        <div>
            <div class="mw-label">Closed out</div>
            <div style="color:var(--ink-900);">{{ $session->closed_at?->format('M j, Y g:i A') ?? '—' }}</div>
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
        </div>
    @else
        <div class="mw-label">Report</div>
        <p style="font-size:13.5px;color:var(--ink-900);white-space:pre-wrap;line-height:1.6;margin-top:6px;">{{ $session->closure_report ?: '—' }}</p>
        <p style="font-size:11px;color:var(--ink-300);margin-top:10px;">Closed before the structured report format was added.</p>
    @endif
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:14px;max-width:640px;">
    This is exactly what the therapist submitted when they closed the session out — it isn't shown to the
    client anywhere in the portal.
</p>
@endsection