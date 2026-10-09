@extends('layouts.app')
@section('title', 'Schedule requests')
@section('content')
{{-- Admin → Schedule requests (2026-10-08, roadmap 75). See Admin\ScheduleRequestController. --}}

<div class="mw-page-head">
    <div>
        <h1>Schedule requests</h1>
        <div class="sub">Moves and cancellations asked for by clients and therapists, and therapists' time off. Nothing changes until you approve.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.calendar') }}" class="btn ghost sm">Open calendar</a></div>
</div>

<nav class="mw-tabs" aria-label="Sections">
    <a href="#changes" class="on">Move or cancel <span class="n">{{ $pending->count() }}</span></a>
    <a href="#timeoff">Time off <span class="n">{{ $timeOff->count() }}</span></a>
    <a href="#history">Recently decided <span class="n">{{ $recent->count() }}</span></a>
</nav>

@if ($errors->any())
    <div class="mw-alert danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
@endif

<h2 id="changes" style="font-size:15px;font-weight:800;color:var(--ink-900);margin:4px 0 10px;scroll-margin-top:80px;">Move or cancel</h2>
@forelse ($pending as $r)
    @php $s = $r->therapySession; @endphp
    <div class="card" style="margin-bottom:12px;display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;" >
        <div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span class="pill {{ $r->type === 'cancel' ? 'danger' : 'warn' }}">{{ $r->type === 'cancel' ? 'Cancel' : 'Move' }}</span>
                <span style="font-size:12px;color:var(--ink-500);">asked by the {{ strtolower($r->requesterLabel()) }}{{ $r->requester ? ' ('.$r->requester->name.')' : '' }} · {{ $r->created_at->diffForHumans() }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-top:10px;">
                <span class="mw-avstack">@include('partials.avatar', ['name' => $s?->clientProfile?->user?->name ?? 'Client', 'size' => 34])@include('partials.avatar', ['name' => $s?->therapistProfile?->user?->name ?? '—', 'size' => 34])</span>
                <div style="font-size:15px;font-weight:800;color:var(--ink-900);">{{ $s?->clientProfile?->user?->name ?? 'Client' }} <span style="font-weight:500;color:var(--ink-500);">with</span> {{ $s?->therapistProfile?->user?->name ?? '—' }}</div>
            </div>
            <div style="font-size:13px;color:var(--ink-700);margin-top:4px;">
                Now: {{ $s?->scheduled_at?->format('l, j M Y · g:i A') ?? '—' }}{{ $s?->isInPerson() ? ' · in person' : '' }}
                @if ($r->type === 'reschedule')
                    <br>Asked for: <strong>{{ $r->proposed_at?->format('l, j M Y · g:i A') }}</strong>
                    @if ($r->mw_clash)
                        <span class="pill danger" style="margin-left:4px;">Therapist not free then</span>
                    @endif
                @endif
            </div>
            @if ($r->reason)
                <div style="font-size:12.5px;color:var(--ink-700);margin-top:6px;">“{{ $r->reason }}”</div>
            @endif
            @if ($s?->status !== 'scheduled')
                <div class="mw-alert danger" style="margin-top:8px;">This session is no longer confirmed ({{ $s?->status }}). Decline the request.</div>
            @endif
            <a href="{{ route('admin.calendar', ['view' => 'week', 'date' => $s?->scheduled_at?->toDateString(), 'focus' => $s?->id]) }}" style="display:inline-block;margin-top:8px;font-size:12px;font-weight:700;color:var(--teal-600, #2f6f66);">See in calendar →</a>
        </div>
        <div>
            <form method="POST" action="{{ route('admin.schedule-requests.approve', $r) }}" style="display:flex;flex-direction:column;gap:8px;">
                @csrf
                @if ($r->type === 'reschedule')
                    <label class="mw-label" style="margin:0;">New time (change it if needed)</label>
                    <input class="mw-field" type="datetime-local" name="proposed_at" value="{{ $r->proposed_at?->format('Y-m-d\TH:i') }}">
                @else
                    @include('calendar._money', ['money' => $r->mw_money])
                @endif
                <input class="mw-field" name="note" maxlength="500" placeholder="Note (optional)" style="font-size:12.5px;">
                <div style="display:flex;gap:6px;">
                    <button type="submit" class="btn primary sm" {{ $s?->status !== 'scheduled' ? 'disabled' : '' }}>Approve</button>
                    <button type="submit" formaction="{{ route('admin.schedule-requests.decline', $r) }}" class="btn ghost sm">Decline</button>
                </div>
            </form>
        </div>
    </div>
@empty
    <div class="card mw-empty" style="margin-bottom:12px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg><div class="big">Nothing waiting</div>No one has asked to move or cancel a session.</div>
@endforelse

<h2 id="timeoff" style="font-size:15px;font-weight:800;color:var(--ink-900);margin:24px 0 10px;scroll-margin-top:80px;">Time off</h2>
@forelse ($timeOff as $t)
    <div class="card" style="margin-bottom:12px;display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;">
        <div>
            <div style="display:flex;align-items:center;gap:10px;">
                @include('partials.avatar', ['name' => $t->therapistProfile?->user?->name, 'size' => 34])
                <div style="font-size:15px;font-weight:800;color:var(--ink-900);">{{ $t->therapistProfile?->user?->name }}</div>
            </div>
            <div style="font-size:13px;color:var(--ink-700);margin-top:4px;">{{ $t->rangeLabel() }}</div>
            @if ($t->reason)<div style="font-size:12.5px;color:var(--ink-700);margin-top:6px;">“{{ $t->reason }}”</div>@endif
            <div style="font-size:11.5px;color:var(--ink-300);margin-top:4px;">Asked {{ $t->created_at->diffForHumans() }}</div>
        </div>
        <div>
            @include('calendar._timeoff-decide', ['t' => $t, 'clashes' => $t->clashingSessions()])
        </div>
    </div>
@empty
    <div class="card mw-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg><div class="big">No time off to review</div></div>
@endforelse

<h2 id="history" style="font-size:15px;font-weight:800;color:var(--ink-900);margin:24px 0 10px;scroll-margin-top:80px;">Recently decided</h2>
<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Decided</th>
                <th>Session</th>
                <th>Request</th>
                <th>Asked by</th>
                <th style="padding-right:16px;">Result</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recent as $r)
                <tr>
                    <td style="padding-left:16px;font-size:12px;white-space:nowrap;">{{ $r->decided_at?->format('j M, g:i A') ?? '—' }}</td>
                    <td style="font-size:12.5px;">@include('partials.person', ['name' => $r->therapySession?->clientProfile?->user?->name ?? 'Client', 'sub' => $r->current_at?->format('j M, g:i A'), 'size' => 28])</td>
                    <td style="font-size:12.5px;">{{ $r->summary() }}</td>
                    <td style="font-size:12.5px;">{{ $r->requesterLabel() }}</td>
                    <td style="padding-right:16px;font-size:12.5px;">
                        <span class="pill {{ $r->status === 'approved' ? 'success' : '' }}">{{ ucfirst($r->status) }}</span>
                        @if (! empty($r->outcome['credit_returned']))<div style="font-size:11px;color:var(--ink-500);">Credit given back</div>@endif
                        @if (! empty($r->outcome['refunded']))<div style="font-size:11px;color:var(--ink-500);">Refund recorded</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">Nothing yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
