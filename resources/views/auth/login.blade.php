@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<div class="mw-auth-shell" style="margin:-28px;">
    <div class="mw-auth-side">
        <div class="icon">🌿</div>
        <div style="font-size:24px;font-weight:800;margin-bottom:8px;">Welcome back</div>
        <div style="font-size:14px;opacity:.85;line-height:1.6;max-width:320px;">
            Licensed therapists. Text, chat, or video. Matched to you in minutes.
        </div>
    </div>

    <div class="mw-auth-form-wrap">
        <div style="width:100%;max-width:340px;">
            <div class="mw-brand" style="margin-bottom:28px;"><span class="dot"></span> Mindwell</div>
            <h1 style="font-size:20px;font-weight:800;margin-bottom:22px;color:var(--ink-900);">Log in to your account</h1>

            @if ($errors->any())
                <div class="mw-alert danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" style="display:flex;flex-direction:column;gap:16px;">
                @csrf
                <div>
                    <label class="mw-label" for="email">Email</label>
                    <input class="mw-field" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div>
                    <label class="mw-label" for="password">Password</label>
                    <input class="mw-field" type="password" id="password" name="password" required>
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-500);">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <button type="submit" class="btn primary block">Log in</button>
            </form>

            <div style="margin-top:22px;font-size:13px;color:var(--ink-500);text-align:center;">
                Don't have an account?
                <a href="{{ route('register') }}" style="color:var(--teal-700);font-weight:700;text-decoration:none;">Sign up</a>
            </div>
        </div>
    </div>
</div>
@endsection