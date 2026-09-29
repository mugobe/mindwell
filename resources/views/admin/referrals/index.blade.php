@extends('layouts.app')
@section('title', 'Referrals')
@section('content')

<div class="dash-head">
    <h1>Referrals</h1>
    <span class="pill {{ $pending->count() > 0 ? 'warn' : 'success' }}">{{ $pending->count() }} awaiting a facility</span>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Referred by</th>
                <th>Referred</th>
                <th style="padding-right:16px;">Assign a facility</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pending as $session)
            <tr>
                <td style="padding-left:16px;">
                    {{ $session->clientProfile?->user?->name ?? 'Unknown client' }}
                    <div style="font-size:10.5px;color:var(--ink-300);">{{ $session->clientProfile?->user?->email }}</div>
                </td>
                <td>{{ $session->therapistProfile?->user?->name ?? '—' }}</td>
                <td>{{ $session->referred_at?->diffForHumans() ?? '—' }}</td>
                <td style="padding-right:16px;">
                    @if ($facilities->isEmpty())
                        <span style="font-size:12px;color:var(--ink-500);">
                            No active facilities yet — <a href="{{ route('admin.facilities.create') }}" style="color:var(--teal-600);">add one</a>.
                        </span>
                    @else
                        <form method="POST" action="{{ route('admin.referrals.assign', $session) }}" style="display:flex;gap:8px;">
                            @csrf
                            <select name="facility_id" class="mw-field" style="width:220px;" required>
                                <option value="">Choose a facility…</option>
                                @foreach ($facilities as $facility)
                                    <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn primary sm">Assign</button>
                        </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px;color:var(--ink-500);">Nobody's waiting on a referral right now.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin:12px 0 28px;">
    A case only shows up here after a therapist closes a session with "Refer out" — the therapist doesn't pick
    the facility themselves, this queue is where the routing team does it.
</p>

<div class="section-title">Recently assigned</div>
<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Facility</th>
                <th style="padding-right:16px;">Assigned</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assigned as $session)
            <tr>
                <td style="padding-left:16px;">{{ $session->clientProfile?->user?->name ?? 'Unknown client' }}</td>
                <td>{{ $session->facility?->name ?? '—' }}</td>
                <td style="padding-right:16px;">{{ $session->referral_assigned_at?->format('M j, Y g:i A') ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="3" style="padding:16px;color:var(--ink-500);">No referrals assigned yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
