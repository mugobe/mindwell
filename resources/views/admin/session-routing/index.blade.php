@extends('layouts.app')
@section('title', 'Session Routing')
@section('content')

{{-- 2026-10-08 redesign: page kit header, jump tabs, headed cards. --}}
<div class="mw-page-head">
    <div>
        <h1>Session routing</h1>
        <div class="sub">Place clients waiting in the lobby, and confirm bookings before clients see them as booked.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.calendar') }}" class="btn ghost sm">Calendar</a>
        <a href="{{ route('admin.venues.index') }}" class="btn ghost sm">Venues</a>
    </div>
</div>

<nav class="mw-tabs" aria-label="Sections">
    <a href="#lobby" class="on">Lobby <span class="n">{{ $waiting->count() }}</span></a>
    <a href="#approve">To approve <span class="n">{{ $pendingApprovals->count() }}</span></a>
    <a href="#inperson">In person <span class="n">{{ $upcomingInPerson->count() }}</span></a>
    <a href="#followups">Recent follow-ups <span class="n">{{ $recentFollowUps->count() }}</span></a>
</nav>

<div class="card" style="padding:0;scroll-margin-top:80px;" id="lobby">
    <div class="mw-card-head">
        <h2>Waiting for a specialist</h2>
        <span class="pill {{ $waiting->count() > 0 ? 'danger' : 'success' }}">{{ $waiting->count() }} waiting</span>
    </div>
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
            @php \App\Models\ClientIntakeDetail::preload($waiting->pluck('client_profile_id')->merge($pendingApprovals->pluck('client_profile_id'))); @endphp
            @forelse ($waiting as $session)
            <tr>
                <td style="padding-left:16px;">
                    @include('partials.person', ['name' => $session->clientProfile?->user?->name, 'fallback' => 'Unknown client', 'sub' => $session->clientProfile?->user?->email])
                    {{-- Richer intake (2026-10-09): what brings them + preferences, shown for information (matching does not use them). --}}
                    @php $mwAb = \App\Models\ClientIntakeDetail::for($session->client_profile_id); @endphp
                    @if ($mwAb && ($mwAb->concernLine(70) || $mwAb->preferenceLines()))
                        <div style="font-size:11.5px;margin:5px 0 0 42px;max-width:300px;line-height:1.45;">
                            @if ($mwAb->concernLine(70))<div style="color:#8a4326;">{{ $mwAb->concernLine(70) }}</div>@endif
                            @if ($mwAb->preferenceLines())<div style="color:var(--ink-500);">{{ implode(' · ', $mwAb->preferenceLines()) }}</div>@endif
                        </div>
                    @endif
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

<div class="card" style="padding:0;margin-top:22px;scroll-margin-top:80px;" id="approve">
    <div class="mw-card-head">
        <h2>Awaiting your approval</h2>
        <span class="pill {{ $pendingApprovals->count() > 0 ? 'warn' : 'success' }}">{{ $pendingApprovals->count() }} pending</span>
    </div>
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
                <td style="padding-left:16px;">
                    @include('partials.person', ['name' => $session->clientProfile?->user?->name, 'fallback' => 'Unknown client'])
                    @php $mwAb = \App\Models\ClientIntakeDetail::for($session->client_profile_id); @endphp
                    @if ($mwAb && ($mwAb->concernLine(70) || $mwAb->preferenceLines()))
                        <div style="font-size:11.5px;margin:5px 0 0 42px;max-width:300px;line-height:1.45;">
                            @if ($mwAb->concernLine(70))<div style="color:#8a4326;">{{ $mwAb->concernLine(70) }}</div>@endif
                            @if ($mwAb->preferenceLines())<div style="color:var(--ink-500);">{{ implode(' · ', $mwAb->preferenceLines()) }}</div>@endif
                        </div>
                    @endif
                </td>
                <td>@include('partials.person', ['name' => $session->therapistProfile?->user?->name, 'size' => 28])</td>
                <td>{{ $session->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td>
                    <span class="pill teal">
                        {{ $session->routing_method === 'therapist_scheduled' ? 'Therapist-scheduled follow-up' : 'Client booked directly' }}
                    </span>
                    @if ($session->isInPerson())
                        <span class="pill success" style="margin-top:4px;">In person</span>
                    @endif
                </td>
                <td style="padding-right:16px;">
                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                        <form method="POST" action="{{ route('admin.session-routing.approve', $session) }}" style="{{ $session->isInPerson() ? 'display:flex;flex-direction:column;gap:6px;min-width:240px;' : '' }}">
                            @csrf
                            @if ($session->isInPerson())
                                {{-- In-person (2026-10-07): say where before confirming. --}}
                                @include('admin.session-routing._location-fields', ['session' => $session, 'venues' => $venues])
                                <button type="submit" class="btn primary sm">Approve &amp; send location</button>
                            @else
                                <button type="submit" class="btn primary sm">Approve</button>
                            @endif
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
@if ($errors->any())
    <div class="mw-alert danger" style="margin-top:10px;">{{ $errors->first() }}</div>
@endif
<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    A client only sees this as confirmed once you approve it here — whether they booked a therapist directly
    themselves or their therapist scheduled a follow-up while closing a previous session. Declining cancels it
    and refunds a session credit if one was used.
</p>

{{-- In-person (2026-10-07): where confirmed in-person sessions happen; change it here. --}}
<div class="card" style="padding:0;margin-top:22px;scroll-margin-top:80px;" id="inperson">
    <div class="mw-card-head">
        <h2>Upcoming in-person sessions</h2>
        <a href="{{ route('admin.venues.index') }}">Manage venues →</a>
    </div>
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Therapist</th>
                <th>When</th>
                <th>Where</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($upcomingInPerson as $session)
            <tr>
                <td style="padding-left:16px;">@include('partials.person', ['name' => $session->clientProfile?->user?->name, 'fallback' => 'Unknown client'])</td>
                <td>@include('partials.person', ['name' => $session->therapistProfile?->user?->name, 'size' => 28])</td>
                <td style="white-space:nowrap;">{{ $session->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td style="font-size:12.5px;max-width:260px;">{{ $session->locationText() ?? 'Not set yet' }}</td>
                <td style="padding-right:16px;text-align:right;position:relative;">
                    <details style="display:inline-block;text-align:left;">
                        <summary class="btn ghost sm" style="list-style:none;cursor:pointer;">Change</summary>
                        <form method="POST" action="{{ route('admin.session-routing.location', $session) }}" class="card" style="position:absolute;right:0;top:100%;z-index:10;width:300px;margin-top:6px;padding:14px;display:flex;flex-direction:column;gap:8px;box-shadow:0 10px 30px rgba(0,0,0,.15);">
                            @csrf
                            @include('admin.session-routing._location-fields', ['session' => $session, 'venues' => $venues])
                            <button type="submit" class="btn primary sm">Save &amp; tell them</button>
                        </form>
                    </details>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">No in-person sessions coming up.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card" style="padding:0;margin-top:22px;scroll-margin-top:80px;" id="followups">
    <div class="mw-card-head">
        <h2>Recently approved follow-ups</h2>
    </div>
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
                <td style="padding-left:16px;">@include('partials.person', ['name' => $session->clientProfile?->user?->name, 'fallback' => 'Unknown client'])</td>
                <td>@include('partials.person', ['name' => $session->therapistProfile?->user?->name, 'size' => 28])</td>
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
