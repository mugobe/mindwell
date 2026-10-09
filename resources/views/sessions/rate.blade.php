@extends('layouts.app')
@section('title', 'Your feedback')
@section('content')
{{-- Added 2026-10-04 — see SessionRatingController. Same stars as the room's thank-you
     screen, but a plain form (works without JavaScript) for the email link and the
     dashboard card. --}}
<style>
    .mw-stars { display:inline-flex; flex-direction:row-reverse; gap:4px; margin:10px 0 6px; }
    .mw-stars input { position:absolute; opacity:0; width:0; height:0; }
    .mw-stars label { font-size:36px; line-height:1; color:#d8dedc; cursor:pointer; padding:2px; transition:color .1s; }
    .mw-stars label:hover, .mw-stars label:hover ~ label, .mw-stars input:checked ~ label { color:#e8a33d; }
    .mw-stars input:focus-visible + label { outline:2px solid var(--teal-600, #2f6f66); outline-offset:2px; border-radius:6px; }
</style>

<div class="card" style="max-width:460px;margin:24px auto;text-align:center;padding:32px 26px;">
    @include('partials.mw-icon-badge', ['tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5z"/>'])
    <h1 style="font-size:19px;font-weight:800;margin:8px 0 6px;color:var(--ink-900);">How was your session?</h1>
    <p style="font-size:13px;color:var(--ink-500);margin:0;">
        With {{ $session->therapistProfile?->user?->name ?? 'your therapist' }} on {{ $session->scheduled_at->format('l, M j') }}.
    </p>

    <form method="POST" action="{{ route('sessions.rate.store', $session) }}">
        @csrf
        <div class="mw-stars" role="radiogroup" aria-label="Rate this session from 1 to 5 stars">
            @for ($star = 5; $star >= 1; $star--)
                <input type="radio" name="rating" id="star-{{ $star }}" value="{{ $star }}" {{ (int) old('rating') === $star ? 'checked' : '' }}>
                <label for="star-{{ $star }}" title="{{ $star }} of 5" aria-label="{{ $star }} of 5 stars">★</label>
            @endfor
        </div>
        @error('rating')<div class="mw-alert danger" style="margin:4px 0 8px;">{{ $message }}</div>@enderror

        <textarea class="mw-field" name="comment" maxlength="2000" rows="3" placeholder="Anything you'd like to tell us? (optional)" style="text-align:left;">{{ old('comment') }}</textarea>
        <div style="font-size:11.5px;color:var(--ink-500);margin-top:6px;">Only the Mindwell team reads comments — your therapist doesn't see them.</div>

        <button type="submit" class="btn coral block" style="border-radius:999px;margin-top:16px;">Send feedback</button>
    </form>

    <form method="POST" action="{{ route('sessions.rate.store', $session) }}" style="margin-top:8px;">
        @csrf
        <input type="hidden" name="skip" value="1">
        <button type="submit" class="btn ghost sm" style="border-radius:999px;">Skip — don't ask about this session again</button>
    </form>
</div>
@endsection
