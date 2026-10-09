{{-- Therapist photo, or their initials if they haven't uploaded one (2026-10-06).
     @include('partials.therapist-avatar', ['therapist' => $profile, 'size' => 56]) --}}
@php
    $mwAvSize = $size ?? 48;
    $mwAvName = $therapist?->user?->name ?? 'Therapist';
    $mwAvInitials = collect(explode(' ', trim($mwAvName)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('');
@endphp
@if ($therapist?->photo_path)
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($therapist->photo_path) }}" alt="{{ $mwAvName }}" style="width:{{ $mwAvSize }}px;height:{{ $mwAvSize }}px;border-radius:50%;object-fit:cover;flex-shrink:0;">
@else
    <div aria-hidden="true" style="width:{{ $mwAvSize }}px;height:{{ $mwAvSize }}px;border-radius:50%;flex-shrink:0;background:var(--mw-teal-tint, #DCEDE8);color:var(--teal-600, #2f6f66);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:{{ round($mwAvSize * 0.36) }}px;">{{ $mwAvInitials }}</div>
@endif
