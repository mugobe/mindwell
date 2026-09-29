@extends('layouts.app')
@section('title', 'My Sessions')
@section('content')

<div class="dash-head">
    <h1>Your sessions</h1>
    <a href="{{ route('therapist.reports.index') }}" class="btn ghost sm">See all reports</a>
</div>

<div class="section-title">Upcoming / to close out ({{ $upcoming->count() }})</div>
<div class="card" style="padding:0;margin-bottom:24px;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>When</th>
                <th>How they were matched</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($upcoming as $session)
            <tr>
                <td style="padding-left:16px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;flex-shrink:0;color:var(--ink-300);"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/></svg>
                        {{ $session->clientProfile?->user?->name ?? 'Unknown client' }}
                    </span>
                </td>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:6px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;flex-shrink:0;color:var(--ink-300);"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg>
                        {{ $session->scheduled_at?->format('M j, Y g:i A') ?? '—' }}
                    </span>
                </td>
                <td>
                    <span class="pill teal">
                        {{ match($session->routing_method) {
                            'algorithmic' => 'Matched automatically',
                            'manual' => 'Routed by staff',
                            'self_booked' => 'Client booked directly',
                            'therapist_scheduled' => 'Follow-up you scheduled',
                            default => '—',
                        } }}
                    </span>
                </td>
                <td style="padding-right:16px;text-align:right;white-space:nowrap;">
                    {{-- Added 2026-09-28: real video via LiveKit. The room page itself
                         handles "not open yet" / "ended", so the button always shows. --}}
                    <a href="{{ route('sessions.room', $session) }}" class="btn coral sm" style="margin-right:6px;">Join video</a>
                    <a href="{{ route('therapist.sessions.close', $session) }}" class="btn primary sm">Close session</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px;color:var(--ink-500);">Nothing scheduled yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section-title">Recently closed</div>
<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Closed</th>
                <th>Outcome</th>
                <th>Report</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentlyClosed as $session)
            <tr>
                <td style="padding-left:16px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;flex-shrink:0;color:var(--ink-300);"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/></svg>
                        {{ $session->clientProfile?->user?->name ?? 'Unknown client' }}
                    </span>
                </td>
                <td>{{ $session->closed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td><span class="pill success">{{ \App\Models\TherapySession::CLOSURE_OUTCOMES[$session->closure_outcome] ?? $session->closure_outcome }}</span></td>
                <td style="font-size:12px;color:var(--ink-500);max-width:260px;">
                    {{ \Illuminate\Support\Str::limit($session->session_summary ?: $session->closure_report ?: '', 70) }}
                </td>
                <td style="padding-right:16px;text-align:right;white-space:nowrap;">
                    <a href="{{ route('therapist.reports.show', $session) }}" class="btn ghost sm">View</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">No closed sessions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    Only your 10 most recent closures show here — see <a href="{{ route('therapist.reports.index') }}" style="color:var(--teal-600);">all your reports</a>
    to search, filter, or download any of them as a PDF.
</p>
@endsection
