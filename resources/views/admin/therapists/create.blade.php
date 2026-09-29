@extends('layouts.app')
@section('title', 'Add Therapist')
@section('content')

<div class="dash-head">
    <h1>Add a therapist</h1>
    <a href="{{ route('admin.therapists.index') }}" class="btn ghost sm">Back to credentialing</a>
</div>

<div class="card" style="max-width:560px;">
    <p style="font-size:12.5px;color:var(--ink-500);margin-bottom:20px;">
        Use this when you're onboarding a therapist directly (already vetted outside the platform) instead of
        having them apply through the public form.
    </p>

    @if ($errors->any())
        <div class="mw-alert danger">
            <ul style="margin:0;padding-left:16px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.therapists.store') }}" style="display:flex;flex-direction:column;gap:16px;">
        @csrf

        <div>
            <label class="mw-label" for="name">Full name</label>
            <input class="mw-field" type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
        </div>
        <div>
            <label class="mw-label" for="email">Email</label>
            <input class="mw-field" type="email" id="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div style="display:flex;gap:12px;">
            <div style="flex:1;">
                <label class="mw-label" for="password">Password</label>
                <input class="mw-field" type="password" id="password" name="password" required>
                <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">Share this with them directly — there's no email invite yet.</div>
            </div>
            <div style="flex:1;">
                <label class="mw-label" for="password_confirmation">Confirm password</label>
                <input class="mw-field" type="password" id="password_confirmation" name="password_confirmation" required>
            </div>
        </div>

        <div style="border-top:1px solid var(--ink-100);margin:4px 0;"></div>
        <div style="font-size:12.5px;font-weight:800;color:var(--ink-700);">Credentials</div>

        <div style="display:flex;gap:12px;">
            <div style="flex:1;">
                <label class="mw-label" for="license_number">License number</label>
                <input class="mw-field" type="text" id="license_number" name="license_number" value="{{ old('license_number') }}" required>
            </div>
            <div style="flex:1;">
                <label class="mw-label" for="license_state">License state / region</label>
                <input class="mw-field" type="text" id="license_state" name="license_state" value="{{ old('license_state') }}" required>
            </div>
        </div>
        <div>
            <label class="mw-label" for="specialties">Specialties</label>
            <input class="mw-field" type="text" id="specialties" name="specialties" value="{{ old('specialties') }}" placeholder="e.g. Anxiety, CBT, Relationships">
        </div>
        <div>
            <label class="mw-label" for="hourly_rate">Session rate (optional)</label>
            <input class="mw-field" type="number" step="0.01" min="0" id="hourly_rate" name="hourly_rate" value="{{ old('hourly_rate') }}" placeholder="e.g. 85">
        </div>

        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-700);">
            <input type="checkbox" name="credentials_verified" value="1" checked>
            Mark credentials as verified immediately (skip the pending queue)
        </label>

        <button type="submit" class="btn primary block" style="margin-top:6px;">Add therapist</button>
    </form>
</div>
@endsection