@extends('layouts.app')
@section('title', 'Change or cancel')
@section('content')
{{-- Client: change or cancel a confirmed session (2026-10-08, roadmap 75). The client asks;
     the team approves. See SessionChangeController. --}}
@php $therapistName = $session->therapistProfile?->user?->name ?? 'your therapist'; @endphp

<div style="max-width:620px;margin:0 auto;">
    <div class="dash-head">
        <h1>Change or cancel</h1>
        <a href="{{ route('sessions.history') }}" class="btn ghost sm" style="border-radius:999px;">My sessions</a>
    </div>

    @if ($errors->any())
        <div class="mw-alert danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
    @endif

    <div class="card" style="margin-bottom:14px;">
        <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;">Your session</div>
        <div style="font-size:17px;font-weight:800;color:var(--ink-900);margin-top:4px;">{{ $session->scheduled_at->format('l, j F · g:i A') }}</div>
        <div style="font-size:13px;color:var(--ink-500);">With {{ $therapistName }} · {{ $session->isInPerson() ? 'In person' : 'Video' }} · {{ $session->duration_minutes }} minutes</div>
    </div>

    @if ($pending)
        <div class="card" style="background:var(--mw-gold, #F6E1A8);border:none;">
            <div style="font-size:14px;font-weight:800;color:var(--ink-900);">Waiting for our team</div>
            <div style="font-size:13px;color:var(--ink-700);margin-top:4px;">
                @if ($pending->requester_role === 'client')
                    You asked to {{ $pending->type === 'cancel' ? 'cancel this session' : 'move it to '.$pending->proposed_at?->format('l, j F · g:i A') }}
                    {{ $pending->created_at->diffForHumans() }}.
                @else
                    A change to this session is being arranged with {{ $therapistName }}.
                @endif
                Your session stays as it is until we confirm — we'll email you.
            </div>
            @if ($pending->requester_role === 'client')
                <form method="POST" action="{{ route('sessions.change.withdraw', $pending) }}" style="margin-top:10px;">
                    @csrf
                    <button type="submit" class="btn ghost sm" style="border-radius:999px;background:#fff;">Withdraw my request</button>
                </form>
            @endif
        </div>
    @else
        <div class="card" style="margin-bottom:14px;">
            <div class="section-title">Move to another time</div>
            @if ($slots->isEmpty())
                <p style="font-size:13px;color:var(--ink-500);margin:0;">{{ $therapistName }} has no other free times in the next two weeks. You can message them, or ask to cancel below.</p>
            @else
                <form method="POST" action="{{ route('sessions.change.store', $session) }}">
                    @csrf
                    <input type="hidden" name="type" value="reschedule">
                    <div style="display:flex;flex-direction:column;gap:10px;max-height:340px;overflow:auto;">
                        @foreach ($slots as $date => $daySlots)
                            <div>
                                <div style="font-size:12px;font-weight:700;color:var(--ink-500);">{{ \Illuminate\Support\Carbon::parse($date)->format('l, j F') }}</div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">
                                    @foreach ($daySlots as $slot)
                                        <label style="display:inline-flex;align-items:center;gap:5px;font-size:12.5px;border:1.5px solid rgba(0,0,0,.12);border-radius:999px;padding:5px 12px;cursor:pointer;">
                                            <input type="radio" name="proposed_at" value="{{ $slot->toDateTimeString() }}" required> {{ $slot->format('g:i A') }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <input class="mw-field" name="reason" maxlength="500" placeholder="Anything we should know? (optional)" style="margin-top:12px;">
                    <button type="submit" class="btn primary" style="margin-top:12px;border-radius:999px;">Ask to move</button>
                </form>
            @endif
        </div>

        <div class="card">
            <div class="section-title">Cancel this session</div>
            <p style="font-size:12.5px;color:var(--ink-500);margin:0 0 10px;">
                Our team confirms cancellations and lets you know what happens to your payment or plan credit.
                Asking at least {{ $noticeHours }} hours before helps us offer the time to someone else.
            </p>
            <form method="POST" action="{{ route('sessions.change.store', $session) }}" onsubmit="return confirm('Ask to cancel this session?');">
                @csrf
                <input type="hidden" name="type" value="cancel">
                <input class="mw-field" name="reason" maxlength="500" placeholder="Reason (optional)">
                <button type="submit" class="btn ghost" style="margin-top:10px;border-radius:999px;">Ask to cancel</button>
            </form>
        </div>
    @endif
</div>
@endsection
