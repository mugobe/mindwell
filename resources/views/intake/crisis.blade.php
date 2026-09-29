@extends('layouts.app')
@section('title', 'Support is available')
@section('content')

<div style="max-width:480px;margin:40px auto 0;text-align:center;">
    <div style="width:60px;height:60px;border-radius:50%;background:var(--danger);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 18px;font-weight:800;">!</div>
    <h1 style="font-size:18px;font-weight:800;color:var(--ink-900);margin-bottom:10px;">You're not alone right now</h1>
    <p style="font-size:13.5px;color:var(--ink-700);line-height:1.7;margin-bottom:26px;">
        Some of your answers suggest you may be going through a lot right now, including thoughts of hurting yourself.
        Please reach out to a real person right away using one of the options below — this isn't something to sit with alone.
    </p>

    <a href="tel:+256800212121" class="btn" style="background:var(--danger);color:#fff;margin-bottom:12px;display:block;">
        Call Mental Health Uganda — 0800 21 21 21 (toll-free)
    </a>

    <div class="card" style="text-align:left;margin-top:24px;">
        <div style="font-size:12px;color:var(--ink-700);line-height:1.7;">
            If you're in immediate physical danger, please go to your nearest hospital or call your local emergency
            services right away. Mindwell's therapist-matching and booking tools are not built for emergencies —
            they're for ongoing support once you're safe.
        </div>
    </div>

    <p style="font-size:11px;color:var(--ink-300);margin-top:22px;line-height:1.6;">
        Your intake answers have been saved. Automatic clinician alerts for responses like this aren't built yet —
        please use one of the numbers above rather than waiting for someone at Mindwell to reach out.
    </p>
</div>
@endsection