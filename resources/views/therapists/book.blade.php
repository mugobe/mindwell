@extends('layouts.app')
@section('title', 'Book a Session')
@section('content')

<div class="dash-head">
    <h1>Book with {{ $therapist->user->name }}</h1>
    <a href="{{ route('therapists.browse') }}" class="btn ghost sm" style="border-radius:999px;">Back to all therapists</a>
</div>

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
                    {{ $slot->format('g:i A') }} · 70 min
                </div>
            </div>
            <button type="submit" class="btn primary sm" style="border-radius:999px;">Book this slot</button>
        </form>
        @endforeach
    </div>
@endif
@endsection
