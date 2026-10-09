@extends('layouts.app')
@section('title', 'My profile')
@section('content')
{{-- Added 2026-10-06 — see Therapist\ProfileController. --}}
@php
    $mwSpecs = is_array($profile->specialties) ? implode(', ', $profile->specialties) : (string) $profile->specialties;
@endphp
{{-- 2026-10-08 redesign (round 3): page kit head. --}}
<div class="mw-page-head">
    <div style="display:flex;align-items:center;gap:14px;">
        @include('partials.therapist-avatar', ['therapist' => $profile, 'size' => 46])
        <div>
            <h1>My profile</h1>
            <div class="sub">This is what clients see when they browse therapists and book you.</div>
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('therapist.stats') }}" class="btn ghost sm">My stats</a>
        <button type="submit" form="mw-profile-form" class="btn primary sm">Save profile</button>
    </div>
</div>

@if ($profile->license_change_requested_at)
    <div class="mw-alert" style="background:#FDF1EC;color:#8a4326;border:1px solid rgba(232,130,94,.35);margin-bottom:14px;">
        Licence change sent for approval on {{ \Illuminate\Support\Carbon::parse($profile->license_change_requested_at)->format('j M Y') }}:
        <strong>{{ $profile->pending_license_number }}</strong> ({{ $profile->pending_license_state }}).
        Your current licence ({{ $profile->license_number }}) stays active until the Mindwell team approves it.
    </div>
@endif

<form id="mw-profile-form" method="POST" action="{{ route('therapist.profile.update') }}" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start;">
    @csrf
    @method('PUT')

    <div class="card" style="display:flex;flex-direction:column;gap:14px;">
        @if ($errors->any())
            <div class="mw-alert danger">{{ $errors->first() }}</div>
        @endif
        <div style="display:flex;align-items:center;gap:14px;">
            @include('partials.therapist-avatar', ['therapist' => $profile, 'size' => 72])
            <div style="flex:1;">
                <label class="mw-label" for="photo">Profile photo</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" style="font-size:12.5px;">
                <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">JPG, PNG or WebP, up to 2 MB. A clear, friendly headshot works best.</div>
                @if ($profile->photo_path)
                    <label style="font-size:12px;color:var(--ink-500);display:flex;gap:6px;align-items:center;margin-top:6px;"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label>
                @endif
            </div>
        </div>
        <div><label class="mw-label" for="name">Full name</label><input class="mw-field" id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div><label class="mw-label" for="professional_title">Professional title</label><input class="mw-field" id="professional_title" name="professional_title" value="{{ old('professional_title', $profile->professional_title) }}" placeholder="e.g. Counselling Psychologist"></div>
        <div><label class="mw-label" for="qualifications">Qualifications</label><textarea class="mw-field" id="qualifications" name="qualifications" rows="3" placeholder="e.g. MSc Clinical Psychology, Makerere University">{{ old('qualifications', $profile->qualifications) }}</textarea></div>
        <div>
            <label class="mw-label" for="languages">Languages spoken</label>
            <input class="mw-field" id="languages" name="languages" value="{{ old('languages', $profile->languages) }}" placeholder="e.g. English, Luganda">
            <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">Separate with commas.</div>
        </div>
    </div>

    <div class="card" style="display:flex;flex-direction:column;gap:14px;">
        <div><label class="mw-label" for="specialties">Areas of speciality</label><input class="mw-field" id="specialties" name="specialties" value="{{ old('specialties', $mwSpecs) }}" placeholder="e.g. Anxiety, Grief, Relationships"></div>
        <div><label class="mw-label" for="bio">About me</label><textarea class="mw-field" id="bio" name="bio" rows="6" placeholder="A few warm sentences about how you work with clients.">{{ old('bio', $profile->bio) }}</textarea></div>
        <div><label class="mw-label" for="hourly_rate">Session fee ({{ config('flutterwave.currency', 'UGX') }}, optional)</label><input class="mw-field" type="number" step="1" min="0" id="hourly_rate" name="hourly_rate" value="{{ old('hourly_rate', $profile->hourly_rate) }}"></div>
        <div style="border-top:1px solid rgba(0,0,0,.06);padding-top:12px;">
            <div style="font-size:13px;font-weight:800;color:var(--ink-900);">Registration / licence</div>
            <div style="font-size:11.5px;color:var(--ink-500);margin:2px 0 10px;">Changes here are checked by the Mindwell team before they take effect.</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div><label class="mw-label" for="license_number">Licence / registration no.</label><input class="mw-field" id="license_number" name="license_number" value="{{ old('license_number', $profile->license_number) }}" required></div>
                <div><label class="mw-label" for="license_state">Registration body</label><input class="mw-field" id="license_state" name="license_state" value="{{ old('license_state', $profile->license_state) }}" required></div>
            </div>
        </div>
        <button type="submit" class="btn coral" style="border-radius:999px;">Save profile</button>
    </div>
</form>
@endsection
