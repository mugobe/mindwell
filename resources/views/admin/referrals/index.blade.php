@extends('layouts.app')
@section('title', 'Referrals')
@section('content')
{{-- Referrals (2026-10-08 redesign): page head, jump tabs, avatars, empty states.
     A case lands here when a therapist closes a session with "Refer out". --}}

<div class="mw-page-head">
    <div>
        <h1>Referrals</h1>
        <div class="sub">Clients a therapist referred out for outside care. Pick the facility here; the therapist doesn't choose it.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.facilities.index') }}" class="btn ghost sm">Facilities</a></div>
</div>

<nav class="mw-tabs" aria-label="Sections">
    <a href="#waiting" class="on">Waiting for a facility <span class="n">{{ $pending->count() }}</span></a>
    <a href="#assigned">Recently assigned <span class="n">{{ $assigned->count() }}</span></a>
</nav>

<div class="card" id="waiting" style="padding:0;margin-bottom:22px;scroll-margin-top:80px;">
    <div class="mw-card-head"><h2>Waiting for a facility</h2></div>
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Referred by</th>
                <th>Referred</th>
                <th style="padding-right:18px;">Assign a facility</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pending as $session)
                @php $c = $session->clientProfile?->user; @endphp
                <tr>
                    <td style="padding-left:18px;">
                        @include('partials.person', [
                            'name' => $c?->name, 'fallback' => 'Unknown client', 'sub' => $c?->email,
                            'href' => $session->clientProfile ? route('admin.clients.show', $session->clientProfile) : null,
                        ])
                    </td>
                    <td>@include('partials.person', ['name' => $session->therapistProfile?->user?->name, 'size' => 28])</td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $session->referred_at?->format('j M Y') ?? '—' }}</div>
                        <div style="font-size:11.5px;color:{{ $session->referred_at && $session->referred_at->lt(now()->subDays(2)) ? 'var(--mw-orange, #E8825E)' : 'var(--ink-300)' }};">{{ $session->referred_at?->diffForHumans() }}</div>
                    </td>
                    <td style="padding-right:18px;">
                        @if ($facilities->isEmpty())
                            <span style="font-size:12px;color:var(--ink-500);">
                                No active facilities yet — <a href="{{ route('admin.facilities.create') }}" style="color:var(--teal-600);font-weight:700;">add one</a>.
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
                <tr>
                    <td colspan="4">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                            <div class="big">Nobody is waiting</div>
                            Referred clients show up here as soon as a therapist closes a session with "Refer out".
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card" id="assigned" style="padding:0;scroll-margin-top:80px;">
    <div class="mw-card-head"><h2>Recently assigned</h2></div>
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Referred by</th>
                <th>Facility</th>
                <th style="padding-right:18px;">Assigned</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assigned as $session)
                @php $c = $session->clientProfile?->user; @endphp
                <tr>
                    <td style="padding-left:18px;">
                        @include('partials.person', [
                            'name' => $c?->name, 'fallback' => 'Unknown client',
                            'href' => $session->clientProfile ? route('admin.clients.show', $session->clientProfile) : null,
                        ])
                    </td>
                    <td>@include('partials.person', ['name' => $session->therapistProfile?->user?->name, 'size' => 28])</td>
                    <td style="font-weight:600;color:var(--ink-900);">{{ $session->facility?->name ?? '—' }}</td>
                    <td style="padding-right:18px;white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $session->referral_assigned_at?->format('j M Y') ?? '—' }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">{{ $session->referral_assigned_at?->format('g:i A') }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13l2.5-8h11L20 13"/><path d="M4 13v6h16v-6h-5l-1 2h-4l-1-2H4z"/></svg>
                            <div class="big">No referrals assigned yet</div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
