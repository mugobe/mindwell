@extends('layouts.app')
@section('title', 'My Sessions')
@section('content')

<div class="dash-head" style="align-items:center;">
    <div style="display:flex;align-items:center;gap:12px;">
        @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-sage, #DEE8CE)', 'icon' => '<rect x="6" y="4.5" width="12" height="16.5" rx="2"/><rect x="9" y="2.5" width="6" height="3.5" rx="1"/><path d="M9 10.5h6M9 14h6M9 17.5h4"/>'])
        <h1>Your sessions</h1>
    </div>
    <a href="{{ route('dashboard') }}" class="btn ghost sm" style="border-radius:999px;">Back to dashboard</a>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">When</th>
                <th>Therapist</th>
                <th>Status</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
                @php
                    [$pillClass, $pillLabel] = match ($session->status) {
                        'pending_payment' => ['warn', 'Awaiting payment'],
                        'routing' => ['warn', 'In the lobby'],
                        'pending_approval' => ['warn', 'Pending approval'],
                        'scheduled' => ['teal', 'Scheduled'],
                        'completed' => ['success', 'Completed'],
                        'cancelled' => ['danger', 'Cancelled'],
                        default => ['', $session->status],
                    };
                @endphp
                <tr>
                    <td style="padding-left:16px;">
                        <span style="display:inline-flex;align-items:center;gap:6px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;flex-shrink:0;color:var(--ink-300);"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg>
                            {{ ($session->scheduled_at ?? $session->created_at)->format('M j, Y g:i A') }}
                        </span>
                    </td>
                    <td>
                        @if ($session->therapistProfile?->user?->name)
                            <span style="display:inline-flex;align-items:center;gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;flex-shrink:0;color:var(--ink-300);"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/></svg>
                                {{ $session->therapistProfile->user->name }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <span class="pill {{ $pillClass }}">{{ $pillLabel }}</span>
                        @if ($session->isInPerson())
                            <span class="pill success">In person</span>
                        @endif
                    </td>
                    <td style="padding-right:16px;text-align:right;">
                        @switch($session->status)
                            @case('pending_payment')
                                <a href="{{ route('sessions.checkout', $session) }}" class="btn primary sm" style="border-radius:999px;">Continue</a>
                                @break
                            @case('routing')
                                <a href="{{ route('sessions.lobby', $session) }}" class="btn primary sm" style="border-radius:999px;">Continue</a>
                                @break
                            @case('pending_approval')
                                <a href="{{ route('sessions.pending-approval', $session) }}" class="btn ghost sm" style="border-radius:999px;">View status</a>
                                @break
                            @case('scheduled')
                                <a href="{{ route('sessions.connected', $session) }}" class="btn ghost sm" style="border-radius:999px;">View details</a>
                                @if ($session->scheduled_at?->isFuture())
                                    <a href="{{ route('sessions.change', $session) }}" class="btn ghost sm" style="border-radius:999px;">Change or cancel</a>
                                @endif
                                @break
                        @endswitch
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="padding:40px 16px;text-align:center;">
                        @include('partials.mw-illustration', ['variant' => 'clipboard', 'size' => 104])
                        <p style="color:var(--ink-500);font-size:13px;margin-top:12px;">
                            No sessions yet — once you start one it'll show up here.
                        </p>
                        <a href="{{ route('therapists.browse') }}" class="btn coral sm" style="display:inline-block;border-radius:999px;margin-top:10px;">Browse therapists</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:14px;">
    Notes and outcomes your therapist records after a session aren't shown here yet — this is just a record
    of when your sessions happened and with whom.
</p>
@endsection
