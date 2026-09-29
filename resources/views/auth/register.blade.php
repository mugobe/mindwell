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
        <div style="font-size:11px;opacity:.65;margin-top:28px;line-height:1.6;max-width:320px;">
            If you're in crisis, call or text 988 any time — Mindwell is not a replacement for emergency care.
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
                <div>
                    <label class="mw-label" for="name">Full name</label>
                    <input class="mw-field" type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
                </div>
                <div>
                    <label class="mw-label" for="email">Email</label>
                    <input class="mw-field" type="email" id="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div>
                    <label class="mw-label" for="password">Password</label>
                    <input class="mw-field" type="password" id="password" name="password" required>
                </div>
                <div>
                    <label class="mw-label" for="password_confirmation">Confirm password</label>
                    <input class="mw-field" type="password" id="password_confirmation" name="password_confirmation" required>
                </div>
                <button type="submit" class="btn coral block">Get started</button>
            </form>

            <div style="margin-top:22px;font-size:13px;color:var(--ink-500);text-align:center;">
                Already have an account?
                <a href="{{ route('login') }}" style="color:var(--teal-700);font-weight:700;text-decoration:none;">Log in</a>
            </div>
            <div style="margin-top:10px;font-size:12.5px;color:var(--ink-300);text-align:center;">
                Are you a therapist?
                <a href="{{ route('register.therapist') }}" style="color:var(--ink-500);font-weight:700;text-decoration:none;">Apply here</a>
            </div>
        </div>
    </div>
</div>
@endsection