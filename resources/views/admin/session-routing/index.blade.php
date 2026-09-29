@extends('layouts.app')
@section('title', 'Session Routing')
@section('content')

<div class="dash-head">
    <h1>Waiting for a specialist</h1>
    <span class="pill {{ $waiting->count() > 0 ? 'warn' : 'success' }}">{{ $waiting->count() }} waiting</span>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Risk level</th>
                <th>Waiting since</th>
                <th style="padding-right:16px;">Assign a therapist</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($waiting as $session)
            <tr>
                <td style="padding-left:16px;">
                    {{ $session->clientProfile?->user?->name ?? 'Unknown client' }}
                    <div style="font-size:10.5px;color:var(--ink-300);">{{ $session->clientProfile?->user?->email }}</div>
                </td>
                <td>
                    @if ($session->intakeAssessment)
                        <span class="pill {{ match($session->intakeAssessment->risk_level) {
                            'crisis', 'high' => 'danger',
                            'moderate' => 'warn',
                            default => 'success',
                        } }}">{{ ucfirst($session->intakeAssessment->risk_level) }}</span>
                    @else
                        —
                    @endif
                </td>
                <td>
                    {{ $session->entered_lobby_at?->diffForHumans() ?? '—' }}
                </td>
                <td style="padding-right:16px;">
                    <form method="POST" action="{{ route('admin.session-routing.assign', $session) }}" style="display:flex;gap:8px;">
                        @csrf
                        <select name="therapist_profile_id" class="mw-field" style="width:220px;" required>
                            <option value="">Choose a therapist…</option>
                            @foreach ($verifiedTherapists as $t)
                                <option value="{{ $t->id }}">{{ $t->user->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn primary sm">Assign</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px;color:var(--ink-500);">Nobody's waiting — the automatic match is keeping up.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    A client only shows up here if our automatic matching couldn't find anyone free right now. Assigning them
    here immediately moves them off their waiting screen.
</p>

<div class="dash-head" style="margin-top:28px;">
    <div class="section-title" style="margin:0;">Awaiting your approval</div>
    <span class="pill {{ $pendingApprovals->count() > 0 ? 'warn' : 'success' }}">{{ $pendingApprovals->count() }} pending</span>
</div>
<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Therapist</th>
                <th>When</th>
                <th>How it was booked</th>
                <th style="padding-right:16px;">Decision</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pendingApprovals as $session)
            <tr>
                <td style="padding-left:16px;">{{ $session->clientProfile?->user?->name ?? 'Unknown client' }}</td>
                <td>{{ $session->therapistProfile?->user?->name ?? '—' }}</td>
                <td>{{ $session->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td>
                    <span class="pill teal">
                        {{ $session->routing_method === 'therapist_scheduled' ? 'Therapist-scheduled follow-up' : 'Client booked directly' }}
                    </span>
                </td>
                <td style="padding-right:16px;">
                    <div style="display:flex;gap:8px;">
                        <form method="POST" action="{{ route('admin.session-routing.approve', $session) }}">
                            @csrf
                            <button type="submit" class="btn primary sm">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.session-routing.decline', $session) }}" onsubmit="return confirm('Decline this session? The client will need to book again.');">
                            @csrf
                            <button type="submit" class="btn ghost sm">Decline</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">Nothing waiting on you right now.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    A client only sees this as confirmed once you approve it here — whether they booked a therapist directly
    themselves or their therapist scheduled a follow-up while closing a previous session. Declining cancels it
    and refunds a session credit if one was used.
</p>

<div class="section-title" style="margin-top:28px;">Recently approved follow-ups</div>
<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Therapist</th>
                <th>When</th>
                <th style="padding-right:16px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentFollowUps as $session)
            <tr>
                <td style="padding-left:16px;">{{ $session->clientProfile?->user?->name ?? 'Unknown client' }}</td>
                <td>{{ $session->therapistProfile?->user?->name ?? '—' }}</td>
                <td>{{ $session->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td style="padding-right:16px;"><span class="pill teal">Scheduled</span></td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px;color:var(--ink-500);">No approved follow-ups yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    Visibility only — these came from a therapist picking a follow-up time directly while closing out a
    session, and you've already approved them above. Nothing to do here.
</p>
@endsection
