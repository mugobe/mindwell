@extends('layouts.app')
@section('title', 'Support is available')
@section('content')
{{-- Updated in Phase 0 (2026-10-05): admins are now alerted straight away (same as a
     check-in — Notifier::crisisAlert), and the numbers come from
     partials.crisis-contacts (24/7 options first; the Mental Health Uganda line is
     weekdays only). Never promises a response time. --}}
<div style="max-width:480px;margin:40px auto 0;text-align:center;">
    <div style="width:60px;height:60px;border-radius:50%;background:var(--danger);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 18px;font-weight:800;">!</div>
    <h1 style="font-size:18px;font-weight:800;color:var(--ink-900);margin-bottom:10px;">You're not alone right now</h1>
    <p style="font-size:13.5px;color:var(--ink-700);line-height:1.7;margin-bottom:24px;">
        Thank you for answering honestly. Some of your answers suggest you may be going through a lot right now,
        including thoughts of hurting yourself. Please reach out to a real person right away — this isn't something to sit with alone.
    </p>

    @include('partials.crisis-contacts')

    <div class="card" style="text-align:left;margin-top:18px;">
        <div style="font-size:12.5px;color:var(--ink-700);line-height:1.7;">
            Your answers have been saved and we've let the Mindwell care team know, so someone can follow up with you.
            Please don't wait for us, though — use one of the numbers above now.
        </div>
    </div>
</div>
@endsection
