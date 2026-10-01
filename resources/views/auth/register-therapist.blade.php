@extends('layouts.app')
@section('title', 'Apply as a therapist')
@section('content')
<div class="mw-auth-shell" style="margin:-28px;">
    <div class="mw-auth-side">
        <div class="icon">🩺</div>
        <div style="font-size:24px;font-weight:800;margin-bottom:8px;">Join Mindwell as a therapist</div>
        <div style="font-size:14px;opacity:.85;line-height:1.6;max-width:320px;">
            Set your own hours, get matched with clients who fit your specialties, and get paid automatically after each session.
        </div>
        <div style="font-size:11px;opacity:.65;margin-top:28px;line-height:1.6;max-width:320px;">
            Every application is reviewed by our credentialing team before you can accept clients — you'll be notified once you're verified.
        </div>
    </div>

    <div class="mw-auth-form-wrap" style="width:520px;">
        <div style="width:100%;max-width:420px;">
            <div class="mw-brand" style="margin-bottom:28px;"><span class="dot"></span> Mindwell</div>
            <h1 style="font-size:20px;font-weight:800;margin-bottom:6px;color:var(--ink-900);">Therapist application</h1>
            <p style="font-size:12.5px;color:var(--ink-500);margin-bottom:22px;">Not a therapist? <a href="{{ route('register') }}" style="color:var(--teal-700);font-weight:700;text-decoration:none;">Sign up as a client</a> instead.</p>

            @if ($errors->any())
                <div class="mw-alert danger">
                    <ul style="margin:0;padding-left:16px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.therapist') }}" style="display:flex;flex-direction:column;gap:16px;">
                @csrf

                <div>
                    <label class="mw-label" for="name">Full name</label>
                    <input class="mw-field" type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
                </div>
                <div>
                    <label class="mw-label" for="email">Email</label>
                    <input class="mw-field" type="email" id="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div>
                    <label class="mw-label" for="phone">Mobile number <span style="font-weight:400;color:var(--ink-300);">(optional)</span></label>
                    <input class="mw-field" type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="0772 123 456" autocomplete="tel">
                    <div style="font-size:11px;color:var(--ink-500);margin-top:4px;">For appointment reminders by SMS. You can change this later.</div>
                    @error('phone')<div class="mw-alert danger" style="margin-top:6px;">{{ $message }}</div>@enderror
                </div>
                <div style="display:flex;gap:12px;">
                    <div style="flex:1;">
                        <label class="mw-label" for="password">Password</label>
                        <input class="mw-field" type="password" id="password" name="password" required>
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
                    <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">Comma-separated. Optional — you can add these later.</div>
                </div>
                <div>
                    <label class="mw-label" for="hourly_rate">Session rate (optional)</label>
                    <input class="mw-field" type="number" step="0.01" min="0" id="hourly_rate" name="hourly_rate" value="{{ old('hourly_rate') }}" placeholder="e.g. 85">
                </div>

                <button type="submit" class="btn coral block" style="margin-top:6px;">Submit application</button>
            </form>

            <div style="margin-top:22px;font-size:13px;color:var(--ink-500);text-align:center;">
                Already have an account?
                <a href="{{ route('login') }}" style="color:var(--teal-700);font-weight:700;text-decoration:none;">Log in</a>
            </div>
        </div>
    </div>
</div>
@endsection
