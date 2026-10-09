@extends('layouts.app')
@section('title', 'Get help now')
@section('content')
{{-- Phase 0 (2026-10-05), from Dr. Ntabade's CLIENT DRAFT: "The urgent-help option
     should take the user to crisis/emergency information rather than making them go
     through registration." Public — no login, no sign-up. --}}
<div style="max-width:480px;margin:24px auto 0;text-align:center;">
    <div style="width:60px;height:60px;border-radius:50%;background:var(--danger, #c0392b);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 18px;font-weight:800;">!</div>
    <h1 style="font-size:20px;font-weight:800;color:var(--ink-900);margin-bottom:8px;">You don't have to face this alone</h1>
    <p style="font-size:13.5px;color:var(--ink-700);line-height:1.7;margin-bottom:22px;">
        If you're in danger or thinking about harming yourself, please reach a real person right now.
        You don't need a Mindwell account for any of these.
    </p>

    @include('partials.crisis-contacts')

    <p style="font-size:12px;color:var(--ink-500);line-height:1.6;margin-top:22px;">
        Mindwell is for ongoing counselling and therapy — it isn't an emergency service and can't send help to you.
        @guest
            When you're safe, you can <a href="{{ route('register') }}" style="color:var(--teal-700, #2f6f66);font-weight:700;">create an account</a> to talk to a therapist.
        @else
            <a href="{{ route('dashboard') }}" style="color:var(--teal-700, #2f6f66);font-weight:700;">Back to Mindwell</a>
        @endguest
    </p>
</div>
@endsection
