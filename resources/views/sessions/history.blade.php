@extends('layouts.app')
@section('title', 'My Sessions')
@section('content')
{{-- 2026-11-01 refresh: "Coming up" cards first, then past sessions grouped by month,
     instead of one long table. Same data as before (SessionHistoryController). --}}
@php
    $mwWhen = fn ($s) => $s->scheduled_at ?? $s->created_at;
    $mwOpen = ['pending_payment', 'routing', 'pending_approval'];
    $mwUpcoming = $sessions->filter(fn ($s) => in_array($s->status, $mwOpen, true)
            || ($s->status === 'scheduled' && $s->scheduled_at && $s->plannedEndAt()?->isFuture()))
        ->sortBy(fn ($s) => $mwWhen($s)->timestamp)->values();
    $mwPast = $sessions->reject(fn ($s) => $mwUpcoming->contains('id', $s->id))
        ->sortByDesc(fn ($s) => $mwWhen($s)->timestamp)
        ->groupBy(fn ($s) => $mwWhen($s)->format('F Y'));
    $mwPill = fn ($s) => match ($s->status) {
        'pending_payment' => ['warn', 'Waiting for payment'],
        'routing' => ['warn', 'In the lobby'],
        'pending_approval' => ['warn', 'Waiting for confirmation'],
        'scheduled' => ['teal', 'Booked'],
        'completed' => ['success', 'Completed'],
        'cancelled' => ['danger', 'Cancelled'],
        default => ['', ucfirst(str_replace('_', ' ', $s->status))],
    };
@endphp

<style>
    .mws-section { margin-bottom: 30px; }
    .mws-up { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
    .mws-card { display: flex; flex-direction: column; gap: 14px; padding: 20px; }
    .mws-top { display: flex; gap: 16px; align-items: center; }
    .mws-cal { flex: 0 0 64px; height: 70px; border-radius: 16px; background: var(--mw-navy); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1; box-shadow: 0 6px 16px rgba(3,76,143,.22); }
    .mws-cal .m { font-size: 10.5px; font-weight: 800; letter-spacing: .12em; opacity: .8; }
    .mws-cal .d { font-size: 26px; font-weight: 800; margin: 3px 0 2px; }
    .mws-cal .w { font-size: 10.5px; font-weight: 700; opacity: .8; }
    .mws-cal.soft { background: var(--mw-navy-tint); color: var(--mw-navy); box-shadow: none; }
    .mws-t { font-size: 16px; font-weight: 800; color: var(--ink-900); }
    .mws-s { font-size: 13px; color: var(--ink-500); margin-top: 2px; }
    .mws-pills { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
    .mws-act { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 14px; border-top: 1px solid var(--mwc-line); }
    .mws-month { font-size: 13px; font-weight: 800; color: var(--ink-500); letter-spacing: .02em; margin: 22px 4px 10px; }
    .mws-month:first-of-type { margin-top: 0; }
    .mws-list { padding: 6px 20px; }
    .mws-row { display: flex; align-items: center; gap: 14px; padding: 12px 0; }
    .mws-row + .mws-row { border-top: 1px solid var(--mwc-line); }
    .mws-row .mws-cal { flex-basis: 46px; height: 48px; border-radius: 13px; }
    .mws-row .mws-cal .d { font-size: 17px; margin: 0 0 2px; }
    .mws-row .who { flex: 1; min-width: 0; }
    .mws-row .who .mws-t { font-size: 14px; }
    @media (max-width: 640px) { .mws-up { grid-template-columns: 1fr; } .mws-list { padding: 4px 16px; } }
</style>

<div class="dash-head">
    <div style="display:flex;align-items:center;gap:12px;margin-right:auto;">
        @include('partials.mw-icon-badge', ['size' => 44, 'margin' => '0', 'tint' => 'var(--mw-navy-tint)', 'icon' => '<rect x="4" y="5" width="16" height="15" rx="2.5"/><path d="M4 9.5h16"/><path d="M8.5 3v4M15.5 3v4"/><path d="M8 13.5h3M8 16.5h6"/>'])
        <div>
            <h1>Your sessions</h1>
            <div class="mwc-sub" style="margin-top:2px;">Upcoming bookings and your session history.</div>
        </div>
    </div>
    <a href="{{ route('therapists.browse') }}" class="btn coral sm" style="border-radius:999px;padding:10px 20px;">Book a session</a>
</div>

@if ($sessions->isEmpty())
    <div class="card" style="text-align:center;padding:44px 24px;">
        @include('partials.mw-illustration', ['variant' => 'clipboard', 'size' => 120])
        <div style="font-size:17px;font-weight:800;color:var(--ink-900);margin-top:10px;">No sessions yet</div>
        <p style="color:var(--ink-500);font-size:13.5px;margin:6px auto 16px;max-width:360px;">When you book a session, it will show here with everything you need to join.</p>
        <a href="{{ route('therapists.browse') }}" class="btn coral" style="display:inline-block;width:auto;border-radius:999px;padding:11px 24px;">Find a therapist</a>
    </div>
@else
    @if ($mwUpcoming->isNotEmpty())
        <section class="mws-section">
            <h2 class="mwc-h2">Coming up</h2>
            <div class="mws-up mwc-stagger">
                @foreach ($mwUpcoming as $session)
                    @php [$pc, $pl] = $mwPill($session); $w = $mwWhen($session); @endphp
                    <div class="card mws-card mwc-lift">
                        <div class="mws-top">
                            <div class="mws-cal {{ $session->status === 'scheduled' ? '' : 'soft' }}" aria-hidden="true">
                                <span class="m">{{ strtoupper($w->format('M')) }}</span><span class="d">{{ $w->format('j') }}</span><span class="w">{{ $w->format('D') }}</span>
                            </div>
                            <div style="min-width:0;">
                                <div class="mws-t">{{ $session->scheduled_at ? $session->scheduled_at->format('g:i A') : 'Time to be set' }}{{ $session->therapistProfile?->user?->name ? ' with '.$session->therapistProfile->user->name : '' }}</div>
                                <div class="mws-s">{{ $session->isInPerson() ? 'In person' : 'Video session' }}{{ $session->duration_minutes ? ', '.$session->duration_minutes.' minutes' : '' }}</div>
                                <div class="mws-pills"><span class="pill {{ $pc }}">{{ $pl }}</span></div>
                            </div>
                        </div>
                        <div class="mws-act">
                            @switch($session->status)
                                @case('pending_payment')
                                    <a href="{{ route('sessions.checkout', $session) }}" class="btn coral sm" style="border-radius:999px;">Pay to confirm</a>
                                    @break
                                @case('routing')
                                    <a href="{{ route('sessions.lobby', $session) }}" class="btn coral sm" style="border-radius:999px;">Back to the lobby</a>
                                    @break
                                @case('pending_approval')
                                    <a href="{{ route('sessions.pending-approval', $session) }}" class="btn ghost sm" style="border-radius:999px;">See status</a>
                                    @break
                                @case('scheduled')
                                    @if ($session->isInPerson())
                                        <a href="{{ route('sessions.connected', $session) }}" class="btn primary sm" style="border-radius:999px;">Where to go</a>
                                    @else
                                        <a href="{{ route('sessions.room', $session) }}" class="btn primary sm" style="border-radius:999px;">Session room</a>
                                        <a href="{{ route('sessions.connected', $session) }}" class="btn ghost sm" style="border-radius:999px;">Details</a>
                                    @endif
                                    @if ($session->scheduled_at?->isFuture())
                                        <a href="{{ route('sessions.change', $session) }}" class="btn ghost sm" style="border-radius:999px;">Move or cancel</a>
                                    @endif
                                    @break
                            @endswitch
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($mwPast->isNotEmpty())
        <section class="mws-section">
            <h2 class="mwc-h2">Past sessions</h2>
            @foreach ($mwPast as $month => $group)
                <div class="mws-month">{{ $month }}</div>
                <div class="card mws-list">
                    @foreach ($group as $session)
                        @php [$pc, $pl] = $mwPill($session); $w = $mwWhen($session); @endphp
                        <div class="mws-row">
                            <div class="mws-cal soft" aria-hidden="true"><span class="d">{{ $w->format('j') }}</span><span class="w">{{ $w->format('D') }}</span></div>
                            <div class="who">
                                <div class="mws-t">{{ $session->therapistProfile?->user?->name ?? 'No therapist assigned' }}</div>
                                <div class="mws-s">{{ $w->format('g:i A') }}, {{ $session->isInPerson() ? 'in person' : 'video' }}</div>
                            </div>
                            <span class="pill {{ $pc }}">{{ $pl }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>
    @endif
@endif

<p style="font-size:12px;color:var(--ink-300);margin-top:6px;">
    Notes your therapist writes after a session are not shown here. This page is a record of when your sessions happened and with whom.
</p>
@endsection
