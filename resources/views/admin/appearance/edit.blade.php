@extends('layouts.app')
@section('title', 'Appearance')
@section('content')
{{-- Admin → Setup → Appearance (2026-11-03). See Admin\AppearanceController. --}}
<style>
    .mw-themes { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px; }
    .mw-theme { position:relative; display:block; cursor:pointer; }
    .mw-theme input { position:absolute; opacity:0; }
    .mw-theme .box { border-radius:16px; background:#fff; box-shadow:0 0 0 1px var(--mw-line); padding:14px; transition:box-shadow .15s; }
    .mw-theme input:checked + .box { box-shadow:0 0 0 2px var(--mw-navy), 0 10px 26px -14px rgba(3,31,63,.4); }
    .mw-theme input:focus-visible + .box { outline:2px solid var(--mw-orange); outline-offset:3px; }
    .mw-theme .pv { display:flex; height:150px; border-radius:11px; overflow:hidden; box-shadow:0 0 0 1px var(--mw-line); }
    .mw-theme .pv .sb { width:34%; padding:12px 10px; display:flex; flex-direction:column; gap:7px; }
    .mw-theme .pv .sb i { display:block; height:8px; border-radius:4px; background:rgba(255,255,255,.22); }
    .mw-theme .pv .sb i.on { background:rgba(255,255,255,.5); box-shadow:-10px 0 0 -6px #EE7800; }
    .mw-theme .pv .mn { flex:1; background:#F4F7FB; padding:12px; display:grid; grid-template-columns:1fr 1fr; gap:8px; align-content:start; }
    .mw-theme .pv .mn b { display:block; height:34px; border-radius:8px; background:#fff; box-shadow:0 0 0 1px var(--mw-line); }
    .mw-theme .pv .mn b.btn-pv { height:14px; width:60%; background:#034C8F; box-shadow:none; }
    .mw-theme .row { display:flex; align-items:center; justify-content:space-between; margin-top:12px; }
    .mw-theme .name { font-weight:800; font-size:14px; color:var(--ink-900); }
    .mw-theme .tick { width:22px; height:22px; border-radius:50%; box-shadow:0 0 0 1.5px var(--ink-300); display:inline-flex; align-items:center; justify-content:center; }
    .mw-theme input:checked + .box .tick { background:var(--mw-navy); box-shadow:none; }
    .mw-theme input:checked + .box .tick::after { content:''; width:9px; height:5px; border:2px solid #fff; border-top:0; border-right:0; transform:rotate(-45deg) translate(1px,-1px); }
</style>

<div class="mw-page-head">
    <div>
        <h1>Appearance</h1>
        <div class="sub">The sidebar colour for every admin and therapist. Buttons, status chips and the rest of each page stay the same.</div>
    </div>
</div>

@if ($errors->any())
    <div class="mw-alert danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('admin.appearance.update') }}">
    @csrf
    @method('PUT')
    <div class="card" style="padding:18px;">
        <div class="mw-card-head" style="padding:0 0 14px;"><h2>Sidebar colour</h2><span style="font-size:12px;color:var(--ink-500);">One choice for the whole clinic</span></div>
        <div class="mw-themes">
            @foreach ($options as $key => $label)
                <label class="mw-theme">
                    <input type="radio" name="sidebar" value="{{ $key }}" {{ $current === $key ? 'checked' : '' }}>
                    <div class="box">
                        <div class="pv">
                            <div class="sb" style="background:{{ $key === 'green' ? 'linear-gradient(180deg,#013A32,#02594B 55%,#047058)' : 'linear-gradient(180deg,#031F3F,#04305E 55%,#053A70)' }};">
                                <i style="width:70%;height:10px;background:rgba(255,255,255,.7);margin-bottom:6px;"></i>
                                <i class="on"></i><i></i><i style="width:80%;"></i><i></i><i style="width:60%;"></i>
                            </div>
                            <div class="mn"><b></b><b></b><b></b><b></b><b class="btn-pv"></b></div>
                        </div>
                        <div class="row"><span class="name">{{ $label }}</span><span class="tick" aria-hidden="true"></span></div>
                    </div>
                </label>
            @endforeach
        </div>
        <div style="margin-top:16px;display:flex;justify-content:flex-end;">
            <button type="submit" class="btn primary sm">Save</button>
        </div>
    </div>
</form>
@endsection
