@extends('layouts.app')
@section('title', 'My details')
@section('content')
{{-- Added 2026-10-06 — see ClientDetailsController. --}}
@php
    // Split an older single "name" into first/last for people who signed up before Phase 0.
    $mwFirst = $user->first_name ?? \Illuminate\Support\Str::before($user->name, ' ');
    $mwLast = $user->last_name ?? (str_contains($user->name, ' ') ? \Illuminate\Support\Str::after($user->name, ' ') : '');
@endphp
<div style="max-width:620px;margin:0 auto;">
    <div class="dash-head" style="align-items:center;">
        <div style="display:flex;align-items:center;gap:12px;">
            @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-teal-tint, #DCEDE8)', 'icon' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/>'])
            <h1>My details</h1>
        </div>
        <a href="{{ route('settings.notifications') }}" class="btn ghost sm" style="border-radius:999px;">Settings</a>
    </div>

    <form method="POST" action="{{ route('settings.details.update') }}" class="card" style="padding:22px;display:flex;flex-direction:column;gap:14px;">
        @csrf
        @method('PUT')
        @if ($errors->any())
            <div class="mw-alert danger">{{ $errors->first() }}</div>
        @endif

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><label class="mw-label" for="first_name">First name</label><input class="mw-field" id="first_name" name="first_name" value="{{ old('first_name', $mwFirst) }}" required></div>
            <div><label class="mw-label" for="last_name">Last name</label><input class="mw-field" id="last_name" name="last_name" value="{{ old('last_name', $mwLast) }}" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><label class="mw-label" for="date_of_birth">Date of birth</label><input class="mw-field" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}" required></div>
            <div>
                <label class="mw-label" for="gender">Gender <span style="font-weight:400;color:var(--ink-300);">(optional)</span></label>
                <select class="mw-field" id="gender" name="gender">
                    <option value="">—</option>
                    @foreach (\App\Models\User::GENDERS as $value => $label)
                        <option value="{{ $value }}" {{ old('gender', $user->gender) === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="mw-label" for="preferred_language">Preferred language for sessions</label>
            <input class="mw-field" id="preferred_language" name="preferred_language" list="mw-languages" value="{{ old('preferred_language', $user->preferred_language) }}" placeholder="e.g. English, Luganda">
            <datalist id="mw-languages">
                @foreach ($languages as $lang)<option value="{{ $lang }}">@endforeach
            </datalist>
        </div>

        <div style="border-top:1px solid rgba(0,0,0,.06);padding-top:14px;">
            <div style="font-size:14px;font-weight:800;color:var(--ink-900);">Emergency contact</div>
            <div style="font-size:12px;color:var(--ink-500);margin:2px 0 10px;">Someone we can reach only if we're seriously worried about your safety. Optional, but recommended.</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><label class="mw-label" for="emergency_contact_name">Name</label><input class="mw-field" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $user->emergency_contact_name) }}"></div>
                <div><label class="mw-label" for="emergency_contact_relationship">Relationship</label><input class="mw-field" id="emergency_contact_relationship" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $user->emergency_contact_relationship) }}" placeholder="e.g. Sister, friend"></div>
            </div>
            <div style="margin-top:12px;"><label class="mw-label" for="emergency_contact_phone">Phone</label><input class="mw-field" type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', \App\Support\UgandaPhone::display($user->emergency_contact_phone)) }}" placeholder="0772 123 456"></div>
        </div>

        <div style="font-size:11.5px;color:var(--ink-500);">Your therapist can see these details. Your journal and mood stay private unless you share them.</div>
        <button type="submit" class="btn coral" style="border-radius:999px;">Save</button>
    </form>

    {{-- Richer intake (2026-10-09, roadmap 63): link to update "About you". --}}
    @if (\App\Models\ClientIntakeDetail::tableReady() && \App\Support\IntakeQuestions::anyOn())
        @php $mwAbout = \App\Models\ClientIntakeDetail::for($user->clientProfile?->id); @endphp
        <a href="{{ route('about.edit') }}" class="card" style="display:flex;align-items:center;gap:14px;margin-top:14px;padding:16px 20px;text-decoration:none;">
            @include('partials.mw-icon-badge', ['size' => 38, 'margin' => '0', 'tint' => '#FDF1EC', 'icon' => '<path d="M4 5h16v11H8l-4 4z"/>'])
            <div style="flex:1;min-width:0;">
                <div style="font-size:14px;font-weight:800;color:var(--ink-900);">About you</div>
                <div style="font-size:12.5px;color:var(--ink-500);">{{ $mwAbout && $mwAbout->hasAnswers() ? 'What brings you here, your goals and preferences. Update any time.' : 'Tell your therapist what brings you here and what you hope for.' }}</div>
            </div>
            <span style="font-size:13px;font-weight:700;color:var(--teal-600, #2f6f66);white-space:nowrap;">{{ $mwAbout && $mwAbout->hasAnswers() ? 'Update' : 'Add' }} →</span>
        </a>
    @endif
</div>
@endsection
