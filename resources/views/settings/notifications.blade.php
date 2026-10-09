@extends('layouts.app')
@section('title', 'Notification settings')
@section('content')
{{-- Added 2026-09-30 — see NotificationSettingsController. --}}
<style>
    .mw-settings { max-width: 620px; margin: 0 auto; }
    .mw-switch-row { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:14px 0; border-top:1px solid rgba(0,0,0,.06); }
    .mw-switch-row:first-of-type { border-top:none; }
    .mw-switch { position:relative; width:44px; height:26px; flex-shrink:0; }
    .mw-switch input { opacity:0; width:0; height:0; position:absolute; }
    .mw-switch span { position:absolute; inset:0; border-radius:13px; background:#cfd6d4; transition:background .15s; cursor:pointer; }
    .mw-switch span::after { content:''; position:absolute; left:3px; top:3px; width:20px; height:20px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.2); transition:transform .15s; }
    .mw-switch input:checked + span { background:var(--teal-600, #2f6f66); }
    .mw-switch input:checked + span::after { transform:translateX(18px); }
    .mw-switch input:focus-visible + span { outline:2px solid var(--teal-600, #2f6f66); outline-offset:2px; }
</style>

<div class="mw-settings">
    <div class="dash-head" style="align-items:center;">
        <div style="display:flex;align-items:center;gap:12px;">
            @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<path d="M6 9a6 6 0 0112 0c0 5 2 6.5 2 6.5H4S6 14 6 9z"/><path d="M10 19a2 2 0 004 0"/>'])
            <h1>Notifications</h1>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.notifications.update') }}" class="card" style="padding:22px;">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="mw-alert danger" style="margin-bottom:14px;">{{ $errors->first() }}</div>
        @endif

        <label class="mw-label" for="phone">Mobile number for SMS</label>
        <input class="mw-field" type="tel" id="phone" name="phone" value="{{ old('phone', $phoneDisplay) }}" placeholder="0772 123 456" autocomplete="tel">
        <div style="font-size:11.5px;color:var(--ink-500);margin-top:5px;">MTN or Airtel Uganda. Leave empty if you don't want text messages.</div>

        <div style="margin-top:18px;">
            <div class="mw-switch-row">
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--ink-900);">Email</div>
                    <div style="font-size:12.5px;color:var(--ink-500);">Confirmations, reminders and new-message alerts, sent to {{ $user->email }}.</div>
                </div>
                <label class="mw-switch">
                    <input type="hidden" name="notify_email" value="0">
                    <input type="checkbox" name="notify_email" value="1" {{ old('notify_email', $user->notify_email ?? true) ? 'checked' : '' }} aria-label="Email notifications">
                    <span></span>
                </label>
            </div>
            <div class="mw-switch-row">
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--ink-900);">SMS</div>
                    <div style="font-size:12.5px;color:var(--ink-500);">Only the time-sensitive ones: confirmations, reminders, and "your appointment is ready". New messages are email only.</div>
                </div>
                <label class="mw-switch">
                    <input type="hidden" name="notify_sms" value="0">
                    <input type="checkbox" name="notify_sms" value="1" {{ old('notify_sms', $user->notify_sms ?? true) ? 'checked' : '' }} aria-label="SMS notifications">
                    <span></span>
                </label>
            </div>
        </div>

        <div style="background:var(--mw-teal-tint, #DCEDE8);border-radius:12px;padding:12px 14px;font-size:12.5px;color:var(--ink-700);margin-top:10px;">
            We keep these discreet: messages only mention "your appointment" or "a new message" — never what it's about.
        </div>

        <button type="submit" class="btn coral" style="border-radius:999px;margin-top:18px;padding-left:26px;padding-right:26px;">Save</button>
    </form>

    {{-- Added 2026-10-06: My details (clients) / My profile (therapists). --}}
    @if ($user->role === 'client' || $user->role === 'therapist')
        <div class="card" style="margin-top:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:16px 22px;">
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--ink-900);">{{ $user->role === 'client' ? 'My details' : 'My profile' }}</div>
                <div style="font-size:12.5px;color:var(--ink-500);">{{ $user->role === 'client' ? 'Name, date of birth, preferred language and emergency contact.' : 'Photo, bio, languages and qualifications clients see.' }}</div>
            </div>
            <a href="{{ $user->role === 'client' ? route('settings.details') : route('therapist.profile.edit') }}" class="btn ghost sm" style="border-radius:999px;">Edit</a>
        </div>
    @endif

    {{-- Added 2026-10-04. --}}
    <div class="card" style="margin-top:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:16px 22px;">
        <div>
            <div style="font-size:14px;font-weight:700;color:var(--ink-900);">Password</div>
            <div style="font-size:12.5px;color:var(--ink-500);">Change the password you use to log in.</div>
        </div>
        <a href="{{ route('password.change') }}" class="btn ghost sm" style="border-radius:999px;">Change password</a>
    </div>

    @if ($user->phone)
        <form method="POST" action="{{ route('settings.notifications.test-sms') }}" style="text-align:center;margin-top:14px;">
            @csrf
            <button type="submit" class="btn ghost sm" style="border-radius:999px;">Send me a test SMS</button>
            @unless ($smsLive)
                <div style="font-size:11px;color:var(--ink-300);margin-top:6px;">SMS isn't switched on for this server yet, so test messages only go to the log.</div>
            @endunless
        </form>
    @endif
</div>
@endsection
