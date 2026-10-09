@extends('layouts.app')
@section('title', 'Sessions')
@section('content')
{{-- Therapist → Sessions (2026-10-08 redesign, round 3): "to close out" pulled out of the
     upcoming list, one main action per row, the rest under ⋯. Data from Therapist\SessionController::index(). --}}
@php
    $toClose = $upcoming->filter(fn ($s) => $s->plannedEndAt()?->isPast())->values();
    $ahead = $upcoming->reject(fn ($s) => $s->plannedEndAt()?->isPast())->values();
    $how = [
        'algorithmic' => 'Matched automatically',
        'manual' => 'Routed by the team',
        'self_booked' => 'Client booked',
        'therapist_scheduled' => 'Follow-up you scheduled',
    ];
    $outcomeShort = [
        'schedule_next_session' => ['success', 'Next session'],
        'refer_to_specialist' => ['warn', 'Referred out'],
        'case_closed' => ['', 'Case closed'],
        'continue_monitoring' => ['teal', 'Monitoring'],
        'other' => ['', 'Other'],
    ];
@endphp

<div class="mw-page-head">
    <div>
        <h1>Sessions</h1>
        <div class="sub">
            @if (! empty($ratingSummary))
                <span title="Individual ratings and comments are only seen by the Mindwell team."><span style="color:#e8a33d;">★</span> <strong style="color:var(--ink-900);">{{ number_format($ratingSummary['average'], 1) }}</strong> average client rating from {{ $ratingSummary['count'] }} sessions</span>
            @else
                Your average client rating shows here once you have {{ \App\Models\SessionRating::MIN_FOR_THERAPIST }} ratings, so no single rating can be worked out.
            @endif
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('therapist.reports.index') }}" class="btn ghost sm">All reports</a>
        <a href="{{ route('therapist.calendar') }}" class="btn primary sm">Calendar</a>
    </div>
</div>

<nav class="mw-tabs" aria-label="Sections">
    <a href="#close" class="{{ $toClose->isNotEmpty() ? 'on' : '' }}">To close out <span class="n" @if ($toClose->isNotEmpty()) style="background:var(--mw-orange,#E8825E);color:#fff;" @endif>{{ $toClose->count() }}</span></a>
    <a href="#upcoming" class="{{ $toClose->isEmpty() ? 'on' : '' }}">Upcoming <span class="n">{{ $ahead->count() }}</span></a>
    <a href="#closed">Recently closed <span class="n">{{ $recentlyClosed->count() }}</span></a>
</nav>

@foreach ([['close', 'To close out', $toClose, 'Sessions that have ended. Write the report to complete the client file.'], ['upcoming', 'Upcoming', $ahead, null]] as [$key, $title, $list, $hint])
    @if ($key === 'upcoming' || $list->isNotEmpty())
        <div class="card" id="{{ $key }}" style="padding:0;margin-bottom:18px;scroll-margin-top:80px;">
            <div class="mw-card-head">
                <h2>{{ $title }}</h2>
                @if ($hint)<span style="font-size:12px;color:var(--ink-500);">{{ $hint }}</span>@endif
            </div>
            <table class="mw-table">
                <thead>
                    <tr>
                        <th style="padding-left:18px;">Client</th>
                        <th>When</th>
                        <th>Type</th>
                        <th style="padding-right:18px;text-align:right;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($list as $session)
                        <tr>
                            <td style="padding-left:18px;">
                                @include('partials.person', ['name' => $session->clientProfile?->user?->name, 'fallback' => 'Unknown client', 'href' => route('therapist.clients.show', $session->client_profile_id), 'sub' => $how[$session->routing_method] ?? null])
                            </td>
                            <td style="white-space:nowrap;">
                                <div style="font-size:13px;color:var(--ink-900);">{{ $session->scheduled_at?->format('D j M Y') ?? '—' }}</div>
                                <div style="font-size:11.5px;color:{{ $key === 'close' ? 'var(--mw-orange,#E8825E)' : 'var(--ink-300)' }};">{{ $session->scheduled_at?->format('g:i A') }} · {{ $session->scheduled_at?->diffForHumans() }}</div>
                            </td>
                            <td>
                                @if ($session->isInPerson())
                                    <span class="pill success" title="{{ $session->locationText() }}">In person</span>
                                    @if ($session->venue)<div style="font-size:11.5px;color:var(--ink-500);margin-top:2px;">{{ $session->venue->name }}</div>@endif
                                @else
                                    <span class="pill teal">Video</span>
                                @endif
                            </td>
                            <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                                @if ($key === 'close' || $session->isInPerson())
                                    <a href="{{ route('therapist.sessions.close', $session) }}" class="btn {{ $key === 'close' ? 'primary' : 'ghost' }} sm">Close out</a>
                                @else
                                    {{-- The room page itself handles "not open yet" / "ended". --}}
                                    <a href="{{ route('sessions.room', $session) }}" class="btn coral sm">Join video</a>
                                @endif
                                <details class="mw-menu" style="vertical-align:middle;margin-left:4px;">
                                    <summary aria-label="More actions">
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                    </summary>
                                    <div class="pop">
                                        <a href="{{ route('therapist.clients.show', $session->client_profile_id) }}">Open client file</a>
                                        <a href="{{ route('therapist.calendar', ['view' => 'week', 'date' => $session->scheduled_at?->toDateString(), 'focus' => $session->id]) }}">See in calendar</a>
                                        @if ($key !== 'close' && ! $session->isInPerson())
                                            <a href="{{ route('therapist.sessions.close', $session) }}">Close out now</a>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="mw-empty">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg>
                                    <div class="big">Nothing booked yet</div>
                                    New bookings appear here once the team approves them.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endforeach

<div class="card" id="closed" style="padding:0;scroll-margin-top:80px;">
    <div class="mw-card-head">
        <h2>Recently closed</h2>
        <a href="{{ route('therapist.reports.index') }}">All reports →</a>
    </div>
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
            @forelse ($recentlyClosed as $session)
                @php [$oc, $ol] = $outcomeShort[$session->closure_outcome] ?? ['', $session->closure_outcome ?: '—']; @endphp
                <tr>
                    <td style="padding-left:18px;">
                        @include('partials.person', ['name' => $session->clientProfile?->user?->name, 'fallback' => 'Unknown client', 'href' => route('therapist.clients.show', $session->client_profile_id), 'sub' => $session->session_number ? 'Session '.$session->session_number : null])
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $session->closed_at?->format('j M Y') ?? '—' }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">{{ $session->closed_at?->format('g:i A') }}</div>
                    </td>
                    <td><span class="pill {{ $oc }}" title="{{ \App\Models\TherapySession::CLOSURE_OUTCOMES[$session->closure_outcome] ?? '' }}">{{ $ol }}</span></td>
                    <td style="font-size:12.5px;color:var(--ink-500);max-width:280px;">
                        {{ \Illuminate\Support\Str::limit($session->session_summary ?: $session->closure_report ?: '—', 80) }}
                    </td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        <a href="{{ route('therapist.reports.show', $session) }}" class="btn ghost sm">Open report</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h4"/></svg>
                            <div class="big">No closed sessions yet</div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.mw-menu[open]').forEach(function (d) { if (! d.contains(e.target)) d.removeAttribute('open'); });
    });
</script>
@endsection
