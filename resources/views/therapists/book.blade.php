@extends('layouts.app')
@section('title', 'Book a Session')
@section('content')

<div class="dash-head">
    <h1>Book with {{ $therapist->user->name }}</h1>
    <a href="{{ route('therapists.browse') }}" class="btn ghost sm" style="border-radius:999px;">Back to all therapists</a>
</div>

{{-- Therapist profile (2026-10-06) — what they wrote in My profile. --}}
@if ($therapist->bio || $therapist->qualifications || $therapist->professional_title || $therapist->photo_path)
    <div class="card" style="display:flex;gap:16px;align-items:flex-start;margin-bottom:18px;">
        @include('partials.therapist-avatar', ['therapist' => $therapist, 'size' => 72])
        <div style="flex:1;">
            <div style="font-size:16px;font-weight:800;color:var(--ink-900);">{{ $therapist->user->name }}</div>
            <div style="font-size:12.5px;color:var(--ink-700);">{{ collect([$therapist->professional_title, $therapist->languages ? 'Speaks '.$therapist->languages : null])->filter()->join(' · ') }}</div>
            @if ($therapist->bio)
                <p style="font-size:13px;color:var(--ink-700);line-height:1.6;margin:8px 0 0;white-space:pre-wrap;">{{ $therapist->bio }}</p>
            @endif
            @if ($therapist->qualifications)
                <div style="font-size:12px;color:var(--ink-500);margin-top:8px;"><strong>Qualifications:</strong> {{ $therapist->qualifications }}</div>
            @endif
            @if ($therapist->specialties)
                <div style="font-size:12px;color:var(--ink-500);margin-top:4px;"><strong>Specialities:</strong> {{ is_array($therapist->specialties) ? implode(', ', $therapist->specialties) : $therapist->specialties }}</div>
            @endif
        </div>
    </div>
@endif

{{-- In-person (2026-10-07): only for therapists admin has switched it on for. --}}
@php
    $mwInPerson = (bool) $therapist->offers_in_person;
    $mwCur = config('flutterwave.currency');
@endphp
@if ($mwInPerson)
    <div class="card" style="margin-bottom:16px;padding:14px 18px;display:flex;gap:18px;flex-wrap:wrap;align-items:center;">
        <div style="font-size:13px;color:var(--ink-700);flex:1;min-width:240px;">
            <strong>Online or in person.</strong> {{ $therapist->user->name }} sees clients both ways. For in-person sessions,
            our team confirms where you'll meet when they approve your booking.
        </div>
        <div style="font-size:12.5px;color:var(--ink-500);">
            Online {{ $mwCur }} {{ number_format((float) config('mindwell.one_off_session_price'), 0) }}
            · In person {{ $mwCur }} {{ number_format((float) config('mindwell.in_person_session_price'), 0) }}
            <div style="font-size:11px;color:var(--ink-300);">A plan or employer credit covers either.</div>
        </div>
    </div>
@endif

@if ($slots->isEmpty())
    <div class="card" style="text-align:center;padding:36px 24px;">
        @include('partials.mw-illustration', ['variant' => 'calendar', 'size' => 104])
        <p style="color:var(--ink-500);font-size:13px;margin-top:12px;">
            No open slots in the next 7 days — check back soon, or
            <a href="{{ route('therapists.browse') }}" style="color:var(--teal-600);">browse other therapists</a>.
        </p>
    </div>
@else
    <div style="display:grid;gap:10px;">
        @foreach ($slots as $slot)
        <form method="POST" action="{{ route('therapists.book.store', $therapist) }}" class="card" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-left:3px solid var(--mw-teal-tint, #DCEDE8);">
            @csrf
            <input type="hidden" name="scheduled_at" value="{{ $slot->toDateTimeString() }}">
            <div>
                <div style="font-weight:700;color:var(--ink-900);display:flex;align-items:center;gap:6px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;flex-shrink:0;"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg>
                    {{ $slot->format('l, M j') }}
                </div>
                <div style="font-size:12.5px;color:var(--ink-500);display:flex;align-items:center;gap:6px;margin-top:2px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;flex-shrink:0;"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/><path d="M9 2h6"/></svg>
                    {{ $slot->format('g:i A') }} · {{ config('mindwell.session_duration_minutes', 70) }} min
                </div>
            </div>
            @if ($mwInPerson)
                <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                    <button type="submit" name="session_mode" value="video" class="btn primary sm" style="border-radius:999px;">Book online</button>
                    <button type="submit" name="session_mode" value="in_person" class="btn ghost sm" style="border-radius:999px;">Book in person</button>
                </div>
            @else
                <button type="submit" name="session_mode" value="video" class="btn primary sm" style="border-radius:999px;">Book this slot</button>
            @endif
        </form>
        @endforeach
    </div>
@endif
@endsection
