@extends('layouts.app')
@section('title', 'Finding your specialist')
@section('content')

<div class="mw-lobby-glow" aria-hidden="true"></div>
<div class="mw-leaves" id="mw-leaves" aria-hidden="true"></div>

<div class="mw-lobby">
    <div class="mw-lobby-orb-wrap">
        <div class="mw-lobby-orb"></div>
        <div class="mw-lobby-orb mw-lobby-orb-ring"></div>
        <div class="mw-lobby-countdown" id="mw-countdown">{{ sprintf('%d:00', $targetMinutes) }}</div>
    </div>

    <div class="mw-lobby-breathe" id="mw-lobby-breathe">Breathe in…</div>

    <h1 class="mw-lobby-title">You're in good hands</h1>
    <p class="mw-lobby-sub">We're finding you an available specialist now — we'll take you straight there the moment someone's ready.</p>

    <div class="pill teal" id="lobby-pill" style="margin:4px auto 8px;display:inline-block;">Finding a specialist…</div>

    <div class="mw-lobby-rotator-card">
        <span class="mw-lobby-quote-mark">&ldquo;</span>
        <div class="mw-lobby-rotator" aria-live="off">
            <span class="mw-lobby-line" id="mw-lobby-line"></span>
        </div>
    </div>

    <div class="mw-lobby-timeout" id="mw-lobby-timeout">
        <p class="mw-lobby-timeout-text">
            This is taking a little longer than usual. You're welcome to keep waiting — we're still looking — or if
            you'd rather, you can pick a therapist and time yourself instead.
        </p>
        <form method="POST" action="{{ route('sessions.cancel', $session) }}">
            @csrf
            <button type="submit" class="btn ghost sm" style="border-radius:999px;">Browse therapists instead</button>
        </form>
    </div>

    <p class="mw-lobby-footnote">
        This page checks automatically — you don't need to refresh it.
    </p>
</div>

<style>
    .mw-lobby{ max-width:560px; margin:32px auto; text-align:center; padding:0 20px; }

    .mw-lobby-orb-wrap{ position:relative; width:150px; height:150px; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; }
    .mw-lobby-orb{
        position:absolute; inset:0; border-radius:50%;
        background:radial-gradient(circle at 35% 32%, var(--teal-300,#7fd1c4), var(--teal-600) 60%, var(--coral-500,#e8886f) 130%);
        opacity:.9; filter:blur(.3px);
        animation:mw-breathe 6s ease-in-out infinite;
        box-shadow:0 0 50px 6px rgba(20,150,140,.18);
    }
    .mw-lobby-orb-ring{
        background:none; border:1.5px solid var(--teal-300,#8fd6ca); opacity:.5;
        animation:mw-breathe-ring 6s ease-in-out infinite;
        box-shadow:none;
    }
    @keyframes mw-breathe{
        0%, 100% { transform:scale(.92); opacity:.85; }
        50% { transform:scale(1.06); opacity:1; }
    }
    @keyframes mw-breathe-ring{
        0%, 100% { transform:scale(1.05); opacity:.35; }
        50% { transform:scale(1.35); opacity:0; }
    }
    .mw-lobby-countdown{
        position:relative; z-index:1; font-size:22px; font-weight:800; color:#fff;
        text-shadow:0 1px 3px rgba(0,0,0,.15); letter-spacing:.02em;
    }

    .mw-lobby-breathe{
        font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
        color:var(--teal-600); margin-bottom:16px; opacity:.85;
        transition:opacity .8s ease;
    }

    .mw-lobby-title{ font-size:20px; font-weight:800; margin-bottom:8px; color:var(--ink-900); }
    .mw-lobby-sub{ font-size:13.5px; color:var(--ink-500); max-width:400px; margin:0 auto 14px; line-height:1.6; }

    /* Given its own card so the rotating message reads as a distinct, held moment
       instead of blending into the surrounding paragraph text. */
    .mw-lobby-rotator-card{
        position:relative; background:linear-gradient(180deg, rgba(255,255,255,.9), rgba(255,247,240,.9));
        border:1px solid rgba(232,136,111,.18); border-radius:16px;
        padding:26px 30px 22px; margin:22px auto 6px; max-width:440px;
        box-shadow:0 8px 24px rgba(20,60,55,.05);
    }
    .mw-lobby-quote-mark{
        position:absolute; top:2px; left:16px; font-size:44px; line-height:1; font-weight:800;
        color:var(--coral-500,#e8886f); opacity:.25; font-family:Georgia, serif;
    }
    .mw-lobby-rotator{
        min-height:52px; display:flex; align-items:center; justify-content:center;
    }
    .mw-lobby-line{
        font-size:16px; font-weight:600; color:var(--ink-900); line-height:1.55; opacity:0;
        transform:translateY(10px); transition:opacity .7s ease, transform .7s ease;
    }
    .mw-lobby-line.mw-visible{ opacity:1; transform:translateY(0); }

    .mw-lobby-footnote{ font-size:11px; color:var(--ink-300); margin-top:22px; }

    .mw-lobby-timeout{
        max-width:400px; margin:22px auto 0; padding-top:18px; border-top:1px solid var(--ink-100);
        opacity:0; max-height:0; overflow:hidden; transition:opacity .6s ease;
    }
    .mw-lobby-timeout.mw-visible{ opacity:1; max-height:200px; }
    .mw-lobby-timeout-text{ font-size:12.5px; color:var(--ink-500); line-height:1.6; margin-bottom:12px; }

    /* Soft warm wash behind the whole scene, beneath the leaves. */
    .mw-lobby-glow{
        position:fixed; inset:0; z-index:-1; pointer-events:none;
        background:
            radial-gradient(circle at 15% 20%, rgba(233,161,110,.12), transparent 45%),
            radial-gradient(circle at 85% 75%, rgba(232,136,111,.10), transparent 45%);
    }

    /* Falling leaves — decorative only, confined to the side margins so they never sit
       behind the readable content. Hidden on narrow screens where there is no side
       whitespace for them to live in. */
    .mw-leaves{ position:fixed; inset:0; overflow:hidden; pointer-events:none; z-index:0; }
    .mw-lobby{ position:relative; z-index:1; }

    .mw-leaf{
        position:absolute; top:-8vh; width:14px; height:14px; opacity:0;
        border-radius:0 100% 0 100%;
        animation-name:mw-leaf-fall; animation-timing-function:linear; animation-iteration-count:infinite;
    }
    .mw-leaf.mw-leaf-a{ background:linear-gradient(135deg,#f0a37c,var(--coral-500,#e8886f)); }
    .mw-leaf.mw-leaf-b{ background:linear-gradient(135deg,#e9c27f,#d99a4e); }
    .mw-leaf.mw-leaf-c{ background:linear-gradient(135deg,#e2896a,#c96b4a); }

    @keyframes mw-leaf-fall{
        0%   { transform:translate(0,0) rotate(0deg); opacity:0; }
        8%   { opacity:.55; }
        25%  { transform:translate(16px, 28vh) rotate(90deg); }
        50%  { transform:translate(-14px, 56vh) rotate(180deg); }
        75%  { transform:translate(16px, 84vh) rotate(270deg); }
        94%  { opacity:.45; }
        100% { transform:translate(-10px, 112vh) rotate(360deg); opacity:0; }
    }

    @media (max-width:900px){ .mw-leaves{ display:none; } }
</style>

<script>
(function () {
    var statusUrl = @json(route('sessions.status', $session));
    var pill = document.getElementById('lobby-pill');

    function poll() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.redirect) {
                    pill.textContent = 'Specialist found — connecting…';
                    window.location.href = data.redirect;
                    return;
                }
                setTimeout(poll, 5000);
            })
            .catch(function () { setTimeout(poll, 8000); });
    }
    setTimeout(poll, 4000);

    // Soft countdown — never goes negative or looks "broken" once time's up. Once it
    // runs out, quietly reveal the "browse instead" bail-out — the client keeps
    // waiting by default, this is just an option now sitting there if they want it.
    var secondsLeft = {{ (int) $targetMinutes }} * 60;
    var countdownEl = document.getElementById('mw-countdown');
    var timeoutEl = document.getElementById('mw-lobby-timeout');
    setInterval(function () {
        if (secondsLeft <= 0) {
            countdownEl.textContent = '···';
            timeoutEl.classList.add('mw-visible');
            return;
        }
        secondsLeft--;
        var m = Math.floor(secondsLeft / 60);
        var s = secondsLeft % 60;
        countdownEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    }, 1000);

    // Breathing cue text, synced to the orb's 6s pulse (mw-breathe keyframes: scale
    // peaks — full "in" — at the 3s midpoint, back to resting — "out" — at 6s/0s).
    var breatheEl = document.getElementById('mw-lobby-breathe');
    var breathingIn = true;
    breatheEl.textContent = 'Breathe in…';
    setInterval(function () {
        breatheEl.style.opacity = 0;
        setTimeout(function () {
            breathingIn = !breathingIn;
            breatheEl.textContent = breathingIn ? 'Breathe in…' : '…and out';
            breatheEl.style.opacity = .85;
        }, 250);
    }, 3000);

    // Gentle rotating lines — a mix of reassurance and light practical prep,
    // replacing what used to be a static readiness checklist.
    var lines = [
        "You're in a safe, private space right now.",
        "Take a slow breath in… and let it out.",
        "You've already done the hardest part by reaching out.",
        "If you can, find a quiet corner just for you.",
        "A stable connection helps — but don't worry if it isn't perfect.",
        "Headphones nearby can help you feel more present, if you have them.",
        "Whatever you're feeling right now is okay.",
        "We won't rush finding the right person for you.",
        "You don't need the right words ready — just show up as you are.",
        "This time is yours."
    ];
    var lineEl = document.getElementById('mw-lobby-line');
    var idx = 0;

    function showLine() {
        lineEl.textContent = lines[idx % lines.length];
        requestAnimationFrame(function () {
            lineEl.classList.add('mw-visible');
        });
        idx++;
    }

    function cycle() {
        lineEl.classList.remove('mw-visible');
        setTimeout(showLine, 400);
    }

    showLine();
    setInterval(cycle, 4800);

    // Falling leaves, kept out of the readable center column — random spot in
    // either side gutter, random size/speed/variant so they don't fall in lockstep.
    var leafContainer = document.getElementById('mw-leaves');
    if (leafContainer && window.innerWidth > 900) {
        var variants = ['mw-leaf-a', 'mw-leaf-b', 'mw-leaf-c'];
        var count = 16;
        for (var i = 0; i < count; i++) {
            var leaf = document.createElement('div');
            var inLeftGutter = Math.random() < 0.5;
            var gutterPos = inLeftGutter
                ? (Math.random() * 16 + 2)
                : (Math.random() * 16 + 82);
            var size = Math.floor(Math.random() * 10) + 10;
            var duration = (Math.random() * 8 + 10).toFixed(1);
            var delay = (Math.random() * -18).toFixed(1);

            leaf.className = 'mw-leaf ' + variants[i % variants.length];
            leaf.style.left = gutterPos + 'vw';
            leaf.style.width = size + 'px';
            leaf.style.height = size + 'px';
            leaf.style.animationDuration = duration + 's';
            leaf.style.animationDelay = delay + 's';

            leafContainer.appendChild(leaf);
        }
    }
})();
</script>
@endsection
