@extends('layouts.app')
@section('title', 'Intake questions')
@section('content')
{{-- Admin → Setup → Intake questions (2026-10-09, roadmap 63). See Admin\IntakeSettingsController
     and App\Support\IntakeQuestions. --}}
@php $onCount = count(array_filter($switches)); @endphp

<div class="mw-page-head">
    <div>
        <h1>Intake questions</h1>
        <div class="sub">The "About you" step new clients see before the PHQ-9 and GAD-7. Switch off anything that makes it too long — every section that's on can be skipped by the client.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.intake.index') }}" class="btn ghost sm">Intake reviews</a></div>
</div>

@if ($errors->any())
    <div class="mw-alert danger" style="margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('admin.intake-settings.update') }}">
    @csrf
    @method('PUT')

    <div class="mw-kpis">
        <div class="mw-kpi"><div class="k">Sections switched on</div><div class="v">{{ $onCount }}<span style="font-size:14px;color:var(--ink-300);font-weight:700;"> / {{ count($sections) }}</span></div><div class="d">{{ $onCount ? 'About '.max(1, $onCount).' short '.($onCount === 1 ? 'screen' : 'screens').' before the 16 screening questions' : 'Clients go straight to the screening questions' }}</div></div>
        <div class="mw-kpi"><div class="k">Who sees the answers</div><div class="v" style="font-size:18px;margin-top:10px;">Therapist &amp; admin</div><div class="d">Shown in the client file. Not used for matching.</div></div>
    </div>

    <div class="card" style="padding:0;margin-bottom:16px;">
        <div class="mw-card-head"><h2>Sections</h2><span style="font-size:12px;color:var(--ink-500);">In the order clients see them</span></div>
        <ul class="mw-switch-list">
            @foreach ($sections as $key => $s)
                <li>
                    <span class="num">{{ $loop->iteration }}</span>
                    <div style="min-width:0;flex:1;">
                        <div class="t">{{ $s['label'] }}</div>
                        <div class="s">{{ $s['hint'] }}</div>
                    </div>
                    <label class="mw-switch" title="Ask this">
                        <input type="checkbox" name="sections[{{ $key }}]" value="1" {{ old('sections') !== null ? (isset(old('sections')[$key]) ? 'checked' : '') : ($switches[$key] ? 'checked' : '') }}>
                        <span class="track"><span class="knob"></span></span>
                        <span class="lbl"></span>
                    </label>
                </li>
            @endforeach
        </ul>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:16px;">
        <div class="card">
            <label class="mw-label" for="topics" style="font-size:13px;font-weight:800;color:var(--ink-900);">Topics clients can pick</label>
            <div style="font-size:12px;color:var(--ink-500);margin:2px 0 8px;">One per line. Used in "What brings you here". Leave empty to use the standard list.</div>
            <textarea class="mw-field" id="topics" name="topics" rows="12" style="font-size:13px;line-height:1.55;">{{ old('topics', implode("\n", $topics)) }}</textarea>
        </div>
        <div class="card">
            <label class="mw-label" for="goals" style="font-size:13px;font-weight:800;color:var(--ink-900);">Goals clients can pick</label>
            <div style="font-size:12px;color:var(--ink-500);margin:2px 0 8px;">One per line. Used in "Goals". Leave empty to use the standard list.</div>
            <textarea class="mw-field" id="goals" name="goals" rows="12" style="font-size:13px;line-height:1.55;">{{ old('goals', implode("\n", $goals)) }}</textarea>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;">
        <button type="submit" class="btn primary">Save intake questions</button>
        <span style="font-size:12px;color:var(--ink-500);">Answers already given keep their wording even if you rename a topic.</span>
    </div>
</form>

<style>
    .mw-switch-list { list-style:none; margin:0; padding:0 8px 8px; }
    .mw-switch-list li { display:flex; align-items:center; gap:14px; padding:13px 10px; }
    .mw-switch-list li + li { border-top:1px solid rgba(16,48,43,.06); }
    .mw-switch-list .num { width:26px; height:26px; flex:0 0 26px; border-radius:50%; background:rgba(47,111,102,.1); color:var(--teal-600, #2f6f66); font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; }
    .mw-switch-list .t { font-size:13.5px; font-weight:700; color:var(--ink-900, #1d2b29); }
    .mw-switch-list .s { font-size:12px; color:var(--ink-500, #5f6f6b); }
    .mw-switch { display:inline-flex; align-items:center; gap:8px; cursor:pointer; user-select:none; }
    .mw-switch input { position:absolute; opacity:0; width:0; height:0; }
    .mw-switch .track { width:40px; height:22px; border-radius:999px; background:#cfd8d5; position:relative; transition:background .15s; }
    .mw-switch .knob { position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.2); transition:left .15s; }
    .mw-switch input:checked + .track { background:var(--teal-600, #2f6f66); }
    .mw-switch input:checked + .track .knob { left:21px; }
    .mw-switch input:focus-visible + .track { outline:2px solid var(--mw-orange, #E8825E); outline-offset:2px; }
    .mw-switch .lbl::after { content:'Off'; font-size:12px; font-weight:700; color:var(--ink-500, #5f6f6b); min-width:22px; display:inline-block; }
    .mw-switch input:checked ~ .lbl::after { content:'On'; color:var(--teal-600, #2f6f66); }
</style>
@endsection
