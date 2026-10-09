@extends('layouts.app')
@section('title', 'Choose a new password')
@section('content')
<div class="mw-auth-shell" style="margin:-28px;">
    <div class="mw-auth-side">
        <div class="icon">🌿</div>
        <div style="font-size:24px;font-weight:800;margin-bottom:8px;">Almost there</div>
        <div style="font-size:14px;opacity:.85;line-height:1.6;max-width:320px;">
            Choose a new password for your Mindwell account.
        </div>
    </div>

    <div class="mw-auth-form-wrap">
        <div style="width:100%;max-width:340px;">
            <div class="mw-brand" style="margin-bottom:28px;"><span class="dot"></span> Mindwell</div>
            {{-- Added 2026-10-04 — see PasswordResetController. --}}
            <h1 style="font-size:20px;font-weight:800;margin-bottom:22px;color:var(--ink-900);">Choose a new password</h1>

            @if ($errors->any())
                <div class="mw-alert danger">
                    {{ $errors->first() }}
                    @if ($errors->has('email'))
                        <a href="{{ route('password.request') }}" style="color:inherit;font-weight:700;">Ask for a new link</a>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" style="display:flex;flex-direction:column;gap:16px;">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label class="mw-label" for="email">Email</label>
                    <input class="mw-field" type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
                </div>
                <div>
                    <label class="mw-label" for="password">New password</label>
                    <input class="mw-field" type="password" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus>
                    <div style="font-size:11.5px;color:var(--ink-500);margin-top:4px;">At least 8 characters.</div>
                </div>
                <div>
                    <label class="mw-label" for="password_confirmation">Type it again</label>
                    <input class="mw-field" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </div>
                <button type="submit" class="btn primary block">Save new password</button>
            </form>
        </div>
    </div>
</div>
@endsection