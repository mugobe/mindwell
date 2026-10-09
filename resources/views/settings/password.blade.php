@extends('layouts.app')
@section('title', 'Change password')
@section('content')
{{-- Added 2026-10-04 — see PasswordChangeController. $forced = they logged in with a
     temporary password from an admin and must choose their own first. --}}
<div style="max-width:460px;margin:0 auto;">
    <div class="dash-head" style="align-items:center;">
        <div style="display:flex;align-items:center;gap:12px;">
            @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-teal-tint, #DCEDE8)', 'icon' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>'])
            <h1>{{ $forced ? 'Choose your own password' : 'Change password' }}</h1>
        </div>
    </div>

    @if ($forced)
        <div class="mw-alert" style="background:var(--mw-gold, #F6E1A8);color:#6b4a12;margin-bottom:14px;">
            You logged in with a temporary password from Mindwell. Please choose your own before continuing.
        </div>
    @endif

    <form method="POST" action="{{ route('password.change.update') }}" class="card" style="padding:22px;display:flex;flex-direction:column;gap:14px;">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="mw-alert danger">{{ $errors->first() }}</div>
        @endif

        <div>
            <label class="mw-label" for="current_password">{{ $forced ? 'Temporary password' : 'Current password' }}</label>
            <input class="mw-field" type="password" id="current_password" name="current_password" required autocomplete="current-password" autofocus>
        </div>
        <div>
            <label class="mw-label" for="password">New password</label>
            <input class="mw-field" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            <div style="font-size:11.5px;color:var(--ink-500);margin-top:4px;">At least 8 characters.</div>
        </div>
        <div>
            <label class="mw-label" for="password_confirmation">Type it again</label>
            <input class="mw-field" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn coral" style="border-radius:999px;margin-top:4px;">Save new password</button>
    </form>

    @if ($forced)
        <form method="POST" action="{{ route('logout') }}" style="text-align:center;margin-top:14px;">
            @csrf
            <button type="submit" class="btn ghost sm" style="border-radius:999px;">Log out</button>
        </form>
    @else
        <p style="text-align:center;margin-top:14px;"><a href="{{ route('settings.notifications') }}" style="font-size:12.5px;color:var(--ink-500);">Back to settings</a></p>
    @endif
</div>
@endsection
