@extends('layouts.app')
@section('title', 'Forgot password')
@section('content')
<div class="mw-auth-shell" style="margin:-28px;">
    <div class="mw-auth-side">
        <div class="icon">🌿</div>
        <div style="font-size:24px;font-weight:800;margin-bottom:8px;">Locked out?</div>
        <div style="font-size:14px;opacity:.85;line-height:1.6;max-width:320px;">
            It happens. We’ll email you a link to choose a new password.
        </div>
    </div>

    <div class="mw-auth-form-wrap">
        <div style="width:100%;max-width:340px;">
            <div class="mw-brand" style="margin-bottom:28px;"><span class="dot"></span> Mindwell</div>
            {{-- Added 2026-10-04 — see PasswordResetController. --}}
            <h1 style="font-size:20px;font-weight:800;margin-bottom:8px;color:var(--ink-900);">Reset your password</h1>
            <p style="font-size:13px;color:var(--ink-500);margin:0 0 20px;line-height:1.55;">Enter the email you use for Mindwell and we'll send you a link to choose a new password.</p>

            @if ($errors->any())
                <div class="mw-alert danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" style="display:flex;flex-direction:column;gap:16px;">
                @csrf
                <div>
                    <label class="mw-label" for="email">Email</label>
                    <input class="mw-field" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                </div>
                <button type="submit" class="btn primary block">Email me a reset link</button>
            </form>

            <div style="margin-top:22px;font-size:13px;color:var(--ink-500);text-align:center;">
                Remembered it?
                <a href="{{ route('login') }}" style="color:var(--teal-700);font-weight:700;text-decoration:none;">Back to log in</a>
            </div>
            <p style="margin-top:16px;font-size:11.5px;color:var(--ink-300);text-align:center;line-height:1.5;">No access to your email any more? Contact Mindwell and an administrator can help you get back in.</p>
        </div>
    </div>
</div>
@endsection