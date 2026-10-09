@extends('layouts.app')
@section('title', "You're connected")
@section('content')

<div class="card" style="max-width:480px;margin:24px auto;text-align:center;padding:40px 28px;">
    @include('partials.mw-icon-badge', ['tint' => 'var(--mw-sage, #DEE8CE)', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>'])
    <h1 style="font-size:19px;font-weight:800;margin-bottom:8px;color:var(--ink-900);">
        You're set
    </h1>
    <p style="font-size:13.5px;color:var(--ink-500);margin-bottom:22px;">
        {{ in_array($session->routing_method, ['self_booked', 'therapist_scheduled'], true) ? "You're booked with" : "You've been matched with" }} <strong>{{ $session->therapistProfile?->user?->name ?? 'your specialist' }}</strong>
        for a {{ $session->duration_minutes }}-minute {{ $session->isInPerson() ? 'in-person ' : '' }}session
        @if ($session->scheduled_at)
            on {{ $session->scheduled_at->format('l, M j \a\t g:i A') }}@endif.
    </p>

    <span class="pill success" style="margin-bottom:22px;">
        {{ match($session->routing_method) {
            'manual' => 'Routed by our team',
            'self_booked' => 'Booked by you',
            'therapist_scheduled' => 'Scheduled by your therapist',
            default => 'Matched automatically',
        } }}
    </span>

    @if ($session->isInPerson())
        {{-- In-person (2026-10-07): where to go instead of a Join button. --}}
        @php $mwVenue = $session->venue; @endphp
        <div class="card" style="text-align:left;background:var(--mw-teal-tint, #DCEDE8);border:none;padding:16px 18px;">
            <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;">In person · where to go</div>
            @if ($session->hasLocation())
                @if ($mwVenue)
                    <div style="font-size:15px;font-weight:800;color:var(--ink-900);margin-top:6px;">{{ $mwVenue->name }}</div>
                    @if ($mwVenue->address)<div style="font-size:13px;color:var(--ink-700);">{{ $mwVenue->address }}</div>@endif
                    @if ($mwVenue->directions)<div style="font-size:12.5px;color:var(--ink-700);margin-top:6px;white-space:pre-wrap;">{{ $mwVenue->directions }}</div>@endif
                @endif
                @if ($session->location_details)
                    <div style="font-size:13px;color:var(--ink-900);margin-top:6px;white-space:pre-wrap;">{{ $session->location_details }}</div>
                @endif
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                    @if ($mwVenue?->map_url)
                        <a href="{{ $mwVenue->map_url }}" target="_blank" rel="noopener" class="btn ghost sm" style="border-radius:999px;background:#fff;">Open map</a>
                    @endif
                    @if ($mwVenue?->phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $mwVenue->phone) }}" class="btn ghost sm" style="border-radius:999px;background:#fff;">Call reception</a>
                    @endif
                </div>
            @else
                <div style="font-size:13px;color:var(--ink-700);margin-top:6px;">Our team will confirm the place shortly — we'll email you.</div>
            @endif
        </div>
        <p style="font-size:11.5px;color:var(--ink-300);margin-top:12px;">
            Please arrive a few minutes early. If you can no longer make it, let us know as soon as you can.
        </p>
    @else
        <div>
            <a href="{{ route('sessions.room', $session) }}" class="btn coral block" style="border-radius:999px;">Join video session</a>
        </div>

        <p style="font-size:11.5px;color:var(--ink-300);margin-top:12px;">
            You can join from {{ config('mindwell.video_join_early_minutes') }} minutes before your start time —
            or earlier, if your therapist opens the room early. Keep the room page open and you'll hear a chime
            the moment they're ready. Use a laptop or phone with a camera, somewhere private and quiet.
        </p>
    @endif

    {{-- 2026-10-08: ask to move or cancel (the team approves). --}}
    @if ($session->status === 'scheduled' && $session->scheduled_at?->isFuture())
        <a href="{{ route('sessions.change', $session) }}" style="display:inline-block;margin-top:14px;font-size:12.5px;font-weight:700;color:var(--teal-600, #2f6f66);">Need to change or cancel?</a>
    @endif
</div>
@endsection
