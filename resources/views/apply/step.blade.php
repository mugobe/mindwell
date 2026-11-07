@extends('layouts.app')
@section('title', 'Your application')
@section('content')
{{-- Therapist onboarding (2026-11-03). One page per step; the step's form is in
     apply/steps/{step}. See TherapistApplicationController. --}}
@include('apply._style')
@php
    $mwKeys = array_keys($steps);
    $mwIdx = array_search($step, $mwKeys, true);
    $mwMissingHere = $missing[$step] ?? [];
@endphp

<div class="ap-wrap">
    <div class="ap-head">
        <div>
            <div style="font-size:12px;font-weight:800;letter-spacing:.1em;color:#EE7800;">THERAPIST APPLICATION · STEP {{ $mwIdx + 1 }} OF {{ count($steps) }}</div>
            <h1>{{ [
                'licence' => 'Your licence and registration',
                'training' => 'Your training and experience',
                'fitness' => 'Fitness to practise',
                'profile' => 'Your profile',
                'consents' => 'Agreements',
                'review' => 'Check and send',
            ][$step] }}</h1>
            <div class="sub">{{ [
                'licence' => 'Who registers you, and a copy of your licence. We check this with the regulator.',
                'training' => 'Your qualifications, how you work and who you work with. This also helps us match clients to you.',
                'fitness' => 'Standard questions every health service asks, and two people who can vouch for your work.',
                'profile' => 'What clients see when they choose a therapist.',
                'consents' => 'Please read each one. Your agreement is recorded with today\'s date and the version you agreed to.',
                'review' => 'Look over your answers. You can still change anything before you send it.',
            ][$step] }}</div>
        </div>
        @if ($app->status === \App\Models\TherapistApplication::CHANGES)
            <span class="mw-chip amber">Changes requested</span>
        @endif
    </div>

    <nav class="ap-steps" aria-label="Application steps">
        @foreach ($steps as $key => $label)
            @php $i = array_search($key, $mwKeys, true); $flag = $key !== 'review' && isset($missing[$key]); @endphp
            <a href="{{ route('apply.step', $key) }}" class="{{ $key === $step ? 'on' : (! $flag && $i < $mwIdx ? 'done' : '') }} {{ $flag && $i < $mwIdx ? 'todo-flag' : '' }}" @if ($key === $step) aria-current="step" @endif>
                <span class="bar"></span><span class="lbl">{{ $i + 1 }}. {{ $label }}</span>
            </a>
        @endforeach
    </nav>

    @if ($app->status === \App\Models\TherapistApplication::CHANGES && $app->decision_note)
        <div class="mw-alert" style="background:#FFF8EC;border:1px solid #F3D7A8;color:#6B4A10;">
            <b>What the team asked you to change:</b><br>{!! nl2br(e($app->decision_note)) !!}
        </div>
    @endif

    @if ($errors->any())
        <div class="mw-alert danger">Please check the highlighted answers below.</div>
    @endif

    {{-- Only once this step has been saved at least once, so a first visit isn't a wall of warnings. --}}
    @if (! in_array($step, ['review', 'consents'], true) && $mwMissingHere && $app->answer($step) !== null && ! $errors->any())
        <div class="ap-missing"><b>Still needed on this step:</b>
            <ul>@foreach ($mwMissingHere as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($step === 'review')
        @include('apply.steps.review')
    @else
        <form method="POST" action="{{ route('apply.save', $step) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @include('apply.steps.'.$step)
            <div class="ap-actions">
                @if ($mwIdx > 0)
                    <button type="submit" name="go" value="back" class="btn muted sm">← Back</button>
                @else
                    <span></span>
                @endif
                <div class="r">
                    <button type="submit" name="go" value="later" class="btn ghost sm">Save and finish later</button>
                    <button type="submit" name="go" value="next" class="btn primary sm">Save and continue →</button>
                </div>
            </div>
        </form>
        @foreach (['licence', 'national_id', 'cv', 'insurance'] as $kind)
            @if ($app->document($kind))
                <form id="rm-{{ $kind }}" method="POST" action="{{ route('apply.document.remove', $kind) }}" style="display:none;">@csrf @method('DELETE')</form>
            @endif
        @endforeach
    @endif
</div>
@endsection
