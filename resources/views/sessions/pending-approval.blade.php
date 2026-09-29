@extends('layouts.app')
@section('title', 'Pending Approval')
@section('content')

<div class="card" style="max-width:480px;margin:24px auto;text-align:center;padding:40px 28px;">
    @include('partials.mw-icon-badge', ['tint' => 'var(--mw-blush, #F5DCE2)', 'icon' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/><path d="M9 2h6"/>'])
    <h1 style="font-size:19px;font-weight:800;margin-bottom:8px;color:var(--ink-900);">
        Just confirming the details
    </h1>
    <p style="font-size:13.5px;color:var(--ink-500);margin-bottom:22px;">
        You're booked in with <strong>{{ $session->therapistProfile?->user?->name ?? 'your specialist' }}</strong>
        for a {{ $session->duration_minutes }}-minute session
        @if ($session->scheduled_at)
            on {{ $session->scheduled_at->format('l, M j \a\t g:i A') }}
        @endif
        . Our team is doing a quick check before it's fully confirmed.
    </p>

    <span class="pill warn" style="margin-bottom:22px;">Pending approval</span>

    <p style="font-size:11.5px;color:var(--ink-300);margin-bottom:20px;">
        This usually only takes a short while. Check back on "My sessions" and this will show as
        "Scheduled" once it's approved.
    </p>

    <a href="{{ route('sessions.history') }}" class="btn ghost sm" style="border-radius:999px;">Check my sessions</a>
</div>
@endsection
