@extends('layouts.app')
@section('title', "You're connected")
@section('content')

<div class="card" style="max-width:480px;margin:24px auto;text-align:center;padding:40px 28px;">
    @include('partials.mw-icon-badge', ['tint' => 'var(--mw-sage, #DEE8CE)', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>'])
    <h1 style="font-size:19px;font-weight:800;margin-bottom:8px;color:var(--ink-900);">
        You're set
    </h1>
    <p style="font-size:13.5px;color:var(--ink-500);margin-bottom:22px;">
        You've been matched with <strong>{{ $session->therapistProfile?->user?->name ?? 'your specialist' }}</strong>
        for a {{ $session->duration_minutes }}-minute session
        @if ($session->scheduled_at)
            on {{ $session->scheduled_at->format('l, M j \a\t g:i A') }}
        @endif
        .
    </p>

    <span class="pill success" style="margin-bottom:22px;">
        {{ match($session->routing_method) {
            'manual' => 'Routed by our team',
            'self_booked' => 'Booked by you',
            'therapist_scheduled' => 'Scheduled by your therapist',
            default => 'Matched automatically',
        } }}
    </span>

    <div>
        <a href="{{ route('sessions.room', $session) }}" class="btn coral block" style="border-radius:999px;">Join video session</a>
    </div>

    <p style="font-size:11.5px;color:var(--ink-300);margin-top:12px;">
        You can join from {{ config('mindwell.video_join_early_minutes') }} minutes before your start time —
        or earlier, if your therapist opens the room early. Keep the room page open and you'll hear a chime
        the moment they're ready. Use a laptop or phone with a camera, somewhere private and quiet.
    </p>
</div>
@endsection
