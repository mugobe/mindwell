@extends('layouts.app')
@section('title', 'Before you continue')
@section('content')
{{-- Phase 0 (2026-10-05) — see ConsentController / EnsureLegalAccepted. --}}
<div style="max-width:480px;margin:24px auto 0;">
    <div class="card" style="padding:26px;">
        <h1 style="font-size:19px;font-weight:800;color:var(--ink-900);margin-bottom:6px;">Before you continue</h1>
        <p style="font-size:13px;color:var(--ink-500);line-height:1.6;margin-bottom:18px;">
            We've added our Terms of Use and Privacy Policy. Please take a moment to read them and confirm below.
        </p>

        @if ($errors->any())
            <div class="mw-alert danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('consent.accept') }}" style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--ink-700);line-height:1.45;">
            @csrf
            <label style="display:flex;gap:8px;align-items:flex-start;">
                <input type="checkbox" name="accept_privacy" value="1" required style="margin-top:2px;">
                <span>I agree to the <a href="{{ route('privacy') }}" target="_blank" style="color:var(--teal-700);font-weight:700;">Privacy Policy</a></span>
            </label>
            <label style="display:flex;gap:8px;align-items:flex-start;">
                <input type="checkbox" name="accept_terms" value="1" required style="margin-top:2px;">
                <span>I agree to the <a href="{{ route('terms') }}" target="_blank" style="color:var(--teal-700);font-weight:700;">Terms of Use</a></span>
            </label>
            @if ($isClient)
                <label style="display:flex;gap:8px;align-items:flex-start;">
                    <input type="checkbox" name="accept_emergency" value="1" required style="margin-top:2px;">
                    <span>I understand that this service does not replace emergency medical care.</span>
                </label>
            @endif
            <button type="submit" class="btn coral" style="border-radius:999px;margin-top:8px;">Continue</button>
        </form>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="text-align:center;margin-top:12px;">
        @csrf
        <button type="submit" class="btn ghost sm" style="border-radius:999px;">Log out</button>
    </form>
</div>
@endsection
