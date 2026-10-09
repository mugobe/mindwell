@extends('layouts.app')
@section('title', 'Sign up')
@section('content')
<div class="mw-auth-shell" style="margin:-28px;">
    <div class="mw-auth-side">
        <div class="icon">🌿</div>
        <div style="font-size:24px;font-weight:800;margin-bottom:8px;">Feel better, on your terms</div>
        <div style="font-size:14px;opacity:.85;line-height:1.6;max-width:320px;">
            Licensed therapists. Text, chat, or video. Matched to you in minutes.
        </div>
        {{-- Was a US crisis number (988) — replaced in Phase 0 (2026-10-05). --}}
        <div style="font-size:11.5px;opacity:.85;margin-top:28px;line-height:1.6;max-width:320px;">
            Mindwell is not a replacement for emergency care. In crisis right now?
            <a href="{{ route('urgent-help') }}" style="color:#fff;font-weight:800;">Get help now →</a>
        </div>
    </div>

    <div class="mw-auth-form-wrap">
        <div style="width:100%;max-width:340px;">
            <div class="mw-brand" style="margin-bottom:28px;"><span class="dot"></span> Mindwell</div>
            <h1 style="font-size:20px;font-weight:800;margin-bottom:22px;color:var(--ink-900);">Create your account</h1>

            @if ($errors->any())
                <div class="mw-alert danger">
                    <ul style="margin:0;padding-left:16px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" style="display:flex;flex-direction:column;gap:16px;">
                @csrf
                {{-- Phase 0 (2026-10-05): first/last name, date of birth, gender — from Dr. Ntabade's CLIENT DRAFT. --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="mw-label" for="first_name">First name</label>
                        <input class="mw-field" type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required autofocus autocomplete="given-name">
                    </div>
                    <div>
                        <label class="mw-label" for="last_name">Last name</label>
                        <input class="mw-field" type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="mw-label" for="date_of_birth">Date of birth</label>
                        <input class="mw-field" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" required max="{{ now()->subYears((int) config('mindwell.min_client_age', 18))->toDateString() }}" autocomplete="bday">
                    </div>
                    <div>
                        <label class="mw-label" for="gender">Gender <span style="font-weight:400;color:var(--ink-300);">(optional)</span></label>
                        <select class="mw-field" id="gender" name="gender">
                            <option value="">—</option>
                            @foreach (\App\Models\User::GENDERS as $value => $label)
                                <option value="{{ $value }}" {{ old('gender') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
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
                <div>
                    <label class="mw-label" for="password">Password</label>
                    <input class="mw-field" type="password" id="password" name="password" required>
                </div>
                <div>
                    <label class="mw-label" for="password_confirmation">Confirm password</label>
                    <input class="mw-field" type="password" id="password_confirmation" name="password_confirmation" required>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;font-size:12.5px;color:var(--ink-700);line-height:1.45;">
                    <label style="display:flex;gap:8px;align-items:flex-start;">
                        <input type="checkbox" name="accept_privacy" value="1" {{ old('accept_privacy') ? 'checked' : '' }} required style="margin-top:2px;">
                        <span>I agree to the <a href="{{ route('privacy') }}" target="_blank" style="color:var(--teal-700);font-weight:700;">Privacy Policy</a></span>
                    </label>
                    <label style="display:flex;gap:8px;align-items:flex-start;">
                        <input type="checkbox" name="accept_terms" value="1" {{ old('accept_terms') ? 'checked' : '' }} required style="margin-top:2px;">
                        <span>I agree to the <a href="{{ route('terms') }}" target="_blank" style="color:var(--teal-700);font-weight:700;">Terms of Use</a></span>
                    </label>
                    <label style="display:flex;gap:8px;align-items:flex-start;">
                        <input type="checkbox" name="accept_emergency" value="1" {{ old('accept_emergency') ? 'checked' : '' }} required style="margin-top:2px;">
                        <span>I understand that this service does not replace emergency medical care.</span>
                    </label>
                </div>
                <button type="submit" class="btn coral block">Get started</button>
            </form>

            <div style="margin-top:22px;font-size:13px;color:var(--ink-500);text-align:center;">
                Already have an account?
                <a href="{{ route('login') }}" style="color:var(--teal-700);font-weight:700;text-decoration:none;">Log in</a>
            </div>
            @include('partials.urgent-help-link')
            <div style="margin-top:10px;font-size:12.5px;color:var(--ink-300);text-align:center;">
                Are you a therapist?
                <a href="{{ route('register.therapist') }}" style="color:var(--ink-500);font-weight:700;text-decoration:none;">Apply here</a>
            </div>
        </div>
    </div>
</div>
@endsection
