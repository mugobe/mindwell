@extends('layouts.app')
@section('title', 'Calendar')
@section('content')
{{-- Therapist → Calendar (2026-10-08, roadmap 75). See Therapist\CalendarController. --}}

<div class="mw-page-head">
    <div>
        <h1>Calendar</h1>
        <div class="sub">Your sessions and time off. Click anything to see it on the right; moves and cancellations go to the team for approval.</div>
    </div>
    <div class="actions">
        <a href="{{ route('therapist.sessions.index') }}" class="btn ghost sm">Sessions</a>
        <a href="{{ route('therapist.availability.index') }}" class="btn ghost sm">Weekly availability</a>
    </div>
</div>

@if ($errors->any())
    <div class="mw-alert danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
@endif

<div style="display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start;" class="mw-cal-layout">
    <div>
        @include('calendar._grid', ['cal' => $cal, 'calRoute' => 'therapist.calendar', 'calQuery' => [], 'focus' => $focus])
    </div>
    <div style="display:flex;flex-direction:column;gap:14px;">
        @include('calendar._panel', ['ev' => $ev, 'viewer' => 'therapist', 'money' => null, 'clashes' => null])

        <div class="card">
            <div style="font-size:14px;font-weight:800;color:var(--ink-900);margin-bottom:10px;">Ask for time off</div>
            <form method="POST" action="{{ route('therapist.time-off.store') }}" style="display:flex;flex-direction:column;gap:8px;">
                @csrf
                <label class="mw-label" style="margin:0;" for="to_from">From</label>
                <input class="mw-field" type="datetime-local" id="to_from" name="starts_at" value="{{ old('starts_at') }}" required min="{{ now()->format('Y-m-d\TH:i') }}">
                <label class="mw-label" style="margin:0;" for="to_to">Until</label>
                <input class="mw-field" type="datetime-local" id="to_to" name="ends_at" value="{{ old('ends_at') }}" required min="{{ now()->format('Y-m-d\TH:i') }}">
                <input class="mw-field" name="reason" maxlength="255" value="{{ old('reason') }}" placeholder="Reason (only the team sees this)">
                <button type="submit" class="btn primary sm">Send to the team</button>
            </form>
            <p style="font-size:11px;color:var(--ink-300);margin:8px 0 0;">Once approved, clients can't book you during it. Sessions already booked then stay until the team moves them.</p>

            @if ($timeOff->isNotEmpty())
                <div style="margin-top:12px;border-top:1px solid rgba(0,0,0,.06);padding-top:10px;">
                    @foreach ($timeOff as $t)
                        <a href="{{ route('therapist.calendar', ['view' => 'week', 'date' => $t->starts_at->toDateString(), 'focus' => $t->id]) }}" style="display:flex;justify-content:space-between;gap:8px;font-size:12px;color:var(--ink-700);text-decoration:none;padding:4px 0;">
                            <span>{{ $t->rangeLabel() }}</span>
                            <span class="mw-dot {{ $t->status === 'approved' ? 'ok' : 'wait' }}">{{ $t->status === 'approved' ? 'Approved' : 'Waiting' }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($requests->isNotEmpty())
            <div class="card">
                <div style="font-size:14px;font-weight:800;color:var(--ink-900);margin-bottom:8px;">Waiting for the team</div>
                @foreach ($requests as $r)
                    <a href="{{ route('therapist.calendar', ['view' => 'week', 'date' => $r->therapySession->scheduled_at->toDateString(), 'focus' => $r->therapy_session_id]) }}" style="display:block;font-size:12px;color:var(--ink-700);text-decoration:none;padding:4px 0;">
                        @include('partials.avatar', ['name' => $r->therapySession->clientProfile?->user?->name ?? 'Client', 'size' => 24]) <strong>{{ $r->therapySession->clientProfile?->user?->name ?? 'Client' }}</strong> · {{ $r->therapySession->scheduled_at->format('D j M, g:i A') }}
                        <div style="color:var(--ink-500);">{{ $r->requesterLabel() }}: {{ $r->summary() }}</div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
<style>@media (max-width: 960px) { .mw-cal-layout { grid-template-columns: 1fr !important; } }</style>
@endsection
