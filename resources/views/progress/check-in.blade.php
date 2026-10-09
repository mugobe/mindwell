@extends('layouts.app')
@section('title', 'Check-in')
{{-- Added 2026-10-03: the two-weekly progress check-in. Same questions, wizard and
     field names as intake (copied from intake/show so the live intake page stays
     untouched) — see ProgressController::store(). --}}
@section('content')

<div class="mw-wizard" style="max-width:640px;margin:0 auto;">

    <div class="mw-wizard-topbar">
        <button type="button" id="mw-back" class="mw-wizard-back" style="visibility:hidden;">&larr; Previous question</button>
        <div class="mw-wizard-progress" id="mw-progress">
            @for ($i = 0; $i < count($phq9Items) + count($gad7Items); $i++)
                <span class="mw-progress-seg"></span>
            @endfor
        </div>
    </div>

    <div class="mw-wizard-intro">
        <h1 style="font-size:19px;font-weight:800;margin-bottom:6px;color:var(--ink-900);">Your check-in</h1>
        <p style="font-size:12.5px;color:var(--ink-500);line-height:1.6;">
            The same questions as your first assessment, so you and your therapist can see what's changing.
            There are no wrong answers. Answer based on <b>the last 2 weeks</b> — it takes about 3 minutes.
            Your answers are shared with your therapist.
        </p>
    </div>

    @if ($errors->any())
        <div class="mw-alert danger">Please answer every question before continuing.</div>
    @endif

    <form method="POST" action="{{ route('progress.check-in.store') }}" id="mw-intake-form">
        @csrf

        <div class="mw-wizard-stage">
            @foreach ($phq9Items as $num => $text)
                <div class="mw-step" data-eyebrow="PHQ-9 · over the last 2 weeks">
                    <div class="mw-step-eyebrow">Over the last 2 weeks, how often have you been bothered by…</div>
                    <div class="mw-step-question">{{ $text }}</div>
                    <div class="mw-step-options">
                        @foreach ($options as $value => $label)
                            <label class="mw-pill-label">
                                <input type="radio" name="phq_{{ $num }}" value="{{ $value }}" required class="mw-radio-input">
                                <span class="mw-pill">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @foreach ($gad7Items as $num => $text)
                <div class="mw-step" data-eyebrow="GAD-7 · over the last 2 weeks">
                    <div class="mw-step-eyebrow">Over the last 2 weeks, how often have you been bothered by…</div>
                    <div class="mw-step-question">{{ $text }}</div>
                    <div class="mw-step-options">
                        @foreach ($options as $value => $label)
                            <label class="mw-pill-label">
                                <input type="radio" name="gad_{{ $num }}" value="{{ $value }}" required class="mw-radio-input">
                                <span class="mw-pill">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <button type="submit" id="mw-finish" class="btn primary block" style="display:none;margin-top:8px;border-radius:999px;">Finish check-in</button>
    </form>

    <noscript>
        <p style="font-size:11px;color:var(--ink-300);margin-top:14px;">
            JavaScript is off, so every question is shown on one page — just answer them all and submit at the bottom.
        </p>
        <button type="submit" form="mw-intake-form" class="btn primary block" style="margin-top:8px;border-radius:999px;">Continue</button>
    </noscript>
</div>

<style>
    .mw-wizard-topbar{ display:flex; align-items:center; gap:16px; margin-bottom:22px; }
    .mw-wizard-back{
        background:none; border:none; padding:0; font-size:12.5px; font-weight:600; color:var(--ink-500);
        cursor:pointer; white-space:nowrap;
    }
    .mw-wizard-back:hover{ color:var(--teal-600); }
    .mw-wizard-progress{ flex:1; display:flex; gap:4px; }
    .mw-progress-seg{ flex:1; height:4px; border-radius:99px; background:var(--ink-100); transition:background .2s ease; }
    .mw-progress-seg.filled{ background:var(--teal-600); }
    .mw-progress-seg.current{ background:var(--coral-500,var(--teal-600)); }

    .mw-wizard-intro{ margin-bottom:18px; }

    .mw-wizard-stage{ position:relative; overflow:hidden; min-height:300px; }

    .mw-step{
        border:1px solid var(--ink-100); border-radius:16px; padding:28px 26px; background:#fff;
        opacity:1; transform:translateX(0); transition:opacity .22s ease, transform .22s ease;
    }
    .mw-step.mw-step-hidden{ display:none; }
    .mw-step.mw-step-enter-right{ opacity:0; transform:translateX(28px); }
    .mw-step.mw-step-enter-left{ opacity:0; transform:translateX(-28px); }

    .mw-step-eyebrow{ font-size:11px; font-weight:700; letter-spacing:.02em; color:var(--teal-600); text-transform:uppercase; margin-bottom:10px; }
    .mw-step-question{ font-size:18px; font-weight:800; color:var(--ink-900); line-height:1.4; margin-bottom:22px; }

    .mw-step-options{ display:flex; flex-direction:column; gap:10px; }
    .mw-pill-label{ display:block; }
    .mw-pill-label .mw-radio-input{ position:absolute; opacity:0; width:0; height:0; }
    .mw-pill{
        display:block; width:100%; text-align:left; padding:15px 20px; border-radius:999px;
        border:1.5px solid var(--ink-100); font-size:14px; font-weight:600; color:var(--ink-700);
        cursor:pointer; transition:all .12s ease;
    }
    .mw-pill:hover{ border-color:var(--teal-300,var(--teal-600)); }
    .mw-radio-input:checked + .mw-pill{
        border-color:var(--teal-600); background:var(--teal-600); color:#fff;
    }
    .mw-radio-input:focus-visible + .mw-pill{ outline:2px solid var(--teal-600); outline-offset:2px; }
</style>

<script>
(function () {
    var steps = Array.from(document.querySelectorAll('.mw-step'));
    var segs = Array.from(document.querySelectorAll('.mw-progress-seg'));
    var backBtn = document.getElementById('mw-back');
    var finishBtn = document.getElementById('mw-finish');
    var current = 0;

    function render(direction) {
        steps.forEach(function (step, i) {
            if (i === current) {
                step.classList.remove('mw-step-hidden', 'mw-step-enter-left', 'mw-step-enter-right');
            } else {
                step.classList.add('mw-step-hidden');
            }
        });

        segs.forEach(function (seg, i) {
            seg.classList.toggle('filled', i < current);
            seg.classList.toggle('current', i === current);
        });

        backBtn.style.visibility = current === 0 ? 'hidden' : 'visible';
        finishBtn.style.display = current === steps.length - 1 ? 'block' : 'none';

        // brief slide-in cue on the newly active step
        var activeStep = steps[current];
        activeStep.classList.add(direction === 'back' ? 'mw-step-enter-left' : 'mw-step-enter-right');
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                activeStep.classList.remove('mw-step-enter-left', 'mw-step-enter-right');
            });
        });
    }

    function goNext() {
        if (current < steps.length - 1) {
            current++;
            render('forward');
        }
    }

    function goBack() {
        if (current > 0) {
            current--;
            render('back');
        }
    }

    backBtn.addEventListener('click', goBack);

    steps.forEach(function (step) {
        step.querySelectorAll('.mw-radio-input').forEach(function (input) {
            input.addEventListener('change', function () {
                // Last question: let the person see their choice and hit "Finish"
                // themselves rather than auto-submitting straight past it.
                if (step === steps[steps.length - 1]) {
                    return;
                }
                setTimeout(goNext, 220);
            });
        });
    });

    render('forward');
})();
</script>
@endsection
