@extends('layouts.app')
@section('title', 'Session ratings')
@section('content')
{{-- Added 2026-10-04 — see Admin\RatingController. Admin-only: therapists never see
     comments, and only see their own average once they have 3+ ratings.
     2026-10-08 redesign: page head, KPI tiles, headed cards, tabs, avatars, empty states. --}}
@php
    $stars = fn (int $n) => '<span style="color:#e8a33d;letter-spacing:1px;" aria-hidden="true">'.str_repeat('★', $n).'</span><span style="color:#d8dedc;letter-spacing:1px;" aria-hidden="true">'.str_repeat('★', 5 - $n).'</span>';
    $total = $overall['count'] + $overall['skipped'];
    $low = ! empty($filters['low']);
    $tid = $filters['therapist'] ?? null;
@endphp

<div class="mw-page-head">
    <div>
        <h1>Session ratings</h1>
        <div class="sub">Clients rate each session as they leave the room, or by email the next day. Only admins see comments.</div>
    </div>
</div>

<div class="mw-kpis">
    <div class="mw-kpi">
        <div class="k">Average, all time</div>
        <div class="v">{{ $overall['count'] ? number_format($overall['average'], 1) : '—' }}<span style="font-size:14px;color:var(--ink-300);font-weight:700;"> / 5</span></div>
        <div class="d">{{ $overall['count'] }} {{ $overall['count'] === 1 ? 'rating' : 'ratings' }}</div>
    </div>
    <div class="mw-kpi">
        <div class="k">Last 30 days</div>
        <div class="v">{{ $overall['count_30'] ? number_format($overall['average_30'], 1) : '—' }}<span style="font-size:14px;color:var(--ink-300);font-weight:700;"> / 5</span></div>
        <div class="d">{{ $overall['count_30'] }} {{ $overall['count_30'] === 1 ? 'rating' : 'ratings' }}</div>
    </div>
    <a class="mw-kpi" href="{{ route('admin.ratings.index', ['low' => 1]) }}">
        <div class="k">Low (1–2 stars)</div>
        <div class="v" style="{{ $overall['low'] ? 'color:#C0392B;' : '' }}">{{ $overall['low'] }}</div>
        <div class="d">{{ $overall['low'] ? 'Worth a look — see them' : 'None so far' }}</div>
    </a>
    <div class="mw-kpi">
        <div class="k">Response rate</div>
        <div class="v">{{ $total ? round($overall['count'] / $total * 100).'%' : '—' }}</div>
        <div class="d">{{ $overall['skipped'] }} skipped</div>
    </div>
</div>

<div class="card" style="padding:0;margin-bottom:18px;">
    <div class="mw-card-head"><h2>By therapist</h2></div>
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Therapist</th>
                <th>Average</th>
                <th>Ratings</th>
                <th>Last 30 days</th>
                <th>Low</th>
                <th style="padding-right:18px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($therapists as $t)
                <tr @if ($tid === $t['id']) style="background:rgba(47,111,102,.05);" @endif>
                    <td style="padding-left:18px;">@include('partials.person', ['name' => $t['name']])</td>
                    <td style="white-space:nowrap;">{!! $stars((int) round($t['average'])) !!} <span style="font-weight:700;margin-left:4px;">{{ number_format($t['average'], 1) }}</span></td>
                    <td>{{ $t['count'] }}</td>
                    <td>{{ $t['average_30'] !== null ? number_format($t['average_30'], 1) : '—' }}</td>
                    <td>
                        @if ($t['low'])
                            <span class="pill danger">{{ $t['low'] }}</span>
                        @else
                            <span style="color:var(--ink-300);">0</span>
                        @endif
                    </td>
                    <td style="padding-right:18px;text-align:right;"><a href="{{ route('admin.ratings.index', ['therapist' => $t['id']]) }}#ratings" class="btn ghost sm">View ratings</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg>
                            <div class="big">No ratings yet</div>
                            Clients are asked as they leave the video room, and once by email the next day.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card" id="ratings" style="padding:0;scroll-margin-top:80px;">
    <div class="mw-card-head">
        <h2>Ratings{{ $filterTherapistName ? ' · '.$filterTherapistName : '' }}</h2>
        @if ($tid)
            <a href="{{ route('admin.ratings.index', array_filter(['low' => $low ? 1 : null])) }}#ratings">All therapists</a>
        @endif
    </div>
    <nav class="mw-tabs" aria-label="Filter ratings" style="padding:0 18px;margin-bottom:0;">
        <a href="{{ route('admin.ratings.index', array_filter(['therapist' => $tid])) }}#ratings" class="{{ $low ? '' : 'on' }}">All</a>
        <a href="{{ route('admin.ratings.index', array_filter(['low' => 1, 'therapist' => $tid])) }}#ratings" class="{{ $low ? 'on' : '' }}">Low only</a>
    </nav>
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Rated</th>
                <th>Client</th>
                <th>Therapist</th>
                <th>Rating</th>
                <th style="padding-right:18px;">Comment</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ratings as $r)
                <tr>
                    <td style="padding-left:18px;white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $r->created_at->format('j M Y') }}</div>
                        @if ($r->therapySession?->scheduled_at)
                            <div style="font-size:11.5px;color:var(--ink-300);">Session of {{ $r->therapySession->scheduled_at->format('j M') }}</div>
                        @endif
                    </td>
                    <td>@include('partials.person', ['name' => $r->clientProfile?->user?->name, 'size' => 28, 'href' => $r->clientProfile ? route('admin.clients.show', $r->clientProfile) : null])</td>
                    <td>@include('partials.person', ['name' => $r->therapistProfile?->user?->name, 'size' => 28])</td>
                    <td style="white-space:nowrap;">
                        {!! $stars($r->rating) !!}
                        <span style="position:absolute;left:-9999px;">{{ $r->rating }} of 5</span>
                    </td>
                    <td style="padding-right:18px;font-size:12.5px;color:var(--ink-700);max-width:380px;">
                        @if ($r->comment)
                            “{{ $r->comment }}”
                        @else
                            <span style="color:var(--ink-300);">No comment</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                            <div class="big">{{ $low ? 'No low ratings' : 'Nothing here yet' }}</div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $ratings->links() }}</div>
@endsection
