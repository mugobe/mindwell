@extends('layouts.app')
@section('title', 'Session Reports')
@section('content')

<div class="dash-head">
    <h1>Session reports</h1>
    <form method="GET" action="{{ route('admin.closed-sessions.index') }}" style="display:flex;gap:8px;">
        <select class="mw-field" name="outcome" style="width:240px;">
            <option value="">All outcomes</option>
            @foreach ($outcomes as $value => $label)
                <option value="{{ $value }}" {{ request('outcome') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn ghost sm">Filter</button>
        @if (request('outcome'))
            <a href="{{ route('admin.closed-sessions.index') }}" class="btn ghost sm">Clear</a>
        @endif
    </form>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Therapist</th>
                <th>Closed</th>
                <th>Outcome</th>
                <th style="padding-right:16px;">Report</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
            <tr>
                <td style="padding-left:16px;">{{ $session->clientProfile?->user?->name ?? 'Unknown client' }}</td>
                <td>{{ $session->therapistProfile?->user?->name ?? 'Unknown therapist' }}</td>
                <td style="white-space:nowrap;">{{ $session->closed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td><span class="pill success">{{ $outcomes[$session->closure_outcome] ?? $session->closure_outcome }}</span></td>
                <td style="padding-right:16px;font-size:12.5px;color:var(--ink-500);max-width:280px;">
                    {{ \Illuminate\Support\Str::limit($session->session_summary ?: $session->closure_report ?: '', 70) }}
                    <br>
                    <a href="{{ route('admin.closed-sessions.show', $session) }}" style="font-size:11.5px;color:var(--teal-600);">View full report</a>
                    · <a href="{{ route('admin.closed-sessions.pdf', $session) }}" style="font-size:11.5px;color:var(--teal-600);">Download PDF</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">No sessions closed out yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $sessions->links() }}</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:14px;">
    Read-only — this mirrors exactly what each therapist recorded when they closed the session out. It isn't
    shown to clients anywhere in the portal.
</p>
@endsection