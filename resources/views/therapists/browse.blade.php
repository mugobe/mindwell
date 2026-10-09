@extends('layouts.app')
@section('title', 'Browse Therapists')
@section('content')

<div class="dash-head" style="display:flex;align-items:center;gap:12px;">
    @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6z"/>'])
    <h1>Choose a therapist</h1>
</div>
<p style="font-size:13px;color:var(--ink-500);margin-bottom:20px;max-width:560px;">
    Prefer to pick someone yourself instead of being matched automatically? Here's everyone currently verified
    on Mindwell.
</p>

<div style="display:grid;gap:14px;">
    @forelse ($therapists as $therapist)
    @php
        // A rotating pastel wash behind each initials avatar, purely decorative — the
        // .avatar class itself (defined in the app's real, invisible-to-this-sandbox
        // stylesheet) is left untouched.
        $mwAvatarTint = [ 'var(--mw-teal-tint, #DCEDE8)', 'var(--mw-coral-tint, #FCE1D3)', 'var(--mw-sage, #DEE8CE)', 'var(--mw-blush, #F5DCE2)' ][$loop->index % 4];
    @endphp
    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
        <div style="display:flex;align-items:center;gap:14px;">
            {{-- Photo from the therapist's own profile (2026-10-06), or initials. --}}
            @if ($therapist->photo_path)
                @include('partials.therapist-avatar', ['therapist' => $therapist, 'size' => 52])
            @else
                <div class="avatar" style="background:{{ $mwAvatarTint }};color:var(--teal-600,#2f6f66);">{{ collect(explode(' ', $therapist->user->name))->map(fn($p) => $p[0] ?? '')->join('') }}</div>
            @endif
            <div>
                <div style="font-weight:700;color:var(--ink-900);">{{ $therapist->user->name }}</div>
                @if ($therapist->professional_title || $therapist->languages)
                    <div style="font-size:12px;color:var(--ink-700);">{{ collect([$therapist->professional_title, $therapist->languages ? 'Speaks '.$therapist->languages : null])->filter()->join(' · ') }}</div>
                @endif
                <div style="font-size:12px;color:var(--ink-500);display:flex;align-items:center;gap:5px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;flex-shrink:0;"><path d="M4 4h9l7 7-9 9-7-7V4z"/><circle cx="9" cy="9" r="1.3" fill="currentColor" stroke="none"/></svg>
                    {{ $therapist->specialties ? implode(', ', $therapist->specialties) : 'General practice' }}
                </div>
                @if ($therapist->bio)
                    <div style="font-size:12.5px;color:var(--ink-500);margin-top:4px;max-width:520px;">{{ \Illuminate\Support\Str::limit($therapist->bio, 140) }}</div>
                @endif
                <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
                    <span class="pill teal">Online</span>
                    @if ($therapist->offers_in_person)
                        <span class="pill success">In person too</span>
                    @endif
                </div>
            </div>
        </div>
        <div style="text-align:right;">
            @if ($therapist->hourly_rate)
                <div style="font-size:12px;color:var(--ink-500);margin-bottom:6px;display:flex;align-items:center;justify-content:flex-end;gap:5px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;flex-shrink:0;"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v9M9.3 9.8c0-1.3 1.2-2.3 2.7-2.3s2.6.8 2.6 2c0 2.7-5.3 1.4-5.3 4.1 0 1.2 1.2 2.2 2.7 2.2s2.7-1 2.7-2.2"/></svg>
                    {{ config('flutterwave.currency', 'UGX') }} {{ number_format((float) $therapist->hourly_rate, 0) }}/session
                </div>
            @endif
            <a href="{{ route('therapists.book', $therapist) }}" class="btn primary sm" style="display:inline-flex;align-items:center;gap:6px;border-radius:999px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;flex-shrink:0;"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg>
                View availability
            </a>
        </div>
    </div>
    @empty
    <div class="card" style="text-align:center;padding:36px 24px;">
        @include('partials.mw-illustration', ['variant' => 'compass', 'size' => 104])
        <p style="color:var(--ink-500);font-size:13px;margin-top:12px;">No verified therapists yet — check back soon.</p>
    </div>
    @endforelse
</div>
@endsection
