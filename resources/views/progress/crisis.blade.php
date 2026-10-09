@extends('layouts.app')
@section('title', 'Support is available')
@section('content')
{{-- Added 2026-10-03 — shown after a check-in where PHQ-9 item 9 (thoughts of
     self-harm) was answered positively. Based on intake/crisis, but this time the
     therapist and admins really are alerted (Notifier::crisisAlert). Never promises a
     response time — the hotline comes first. --}}
<div style="max-width:480px;margin:40px auto 0;text-align:center;">
    <div style="width:60px;height:60px;border-radius:50%;background:var(--danger);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 18px;font-weight:800;">!</div>
    <h1 style="font-size:18px;font-weight:800;color:var(--ink-900);margin-bottom:10px;">You're not alone right now</h1>
    <p style="font-size:13.5px;color:var(--ink-700);line-height:1.7;margin-bottom:26px;">
        Thank you for answering honestly. Some of your answers suggest you may be having thoughts of hurting yourself.
        Please reach out to a real person right away using one of the numbers below — this isn't something to sit with alone.
    </p>

    @include('partials.crisis-contacts')

    <div class="card" style="text-align:left;margin-top:18px;">
        <div style="font-size:12.5px;color:var(--ink-700);line-height:1.7;">
            We've let your therapist and the Mindwell care team know, so someone can follow up with you.
            Please don't wait for us, though — use one of the numbers above now.
        </div>
    </div>

    <p style="margin-top:22px;">
        <a href="{{ route('progress.index') }}" style="font-size:12.5px;color:var(--ink-500);">Go to My progress</a>
    </p>
</div>
@endsection
