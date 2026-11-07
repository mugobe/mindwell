@extends('layouts.app')
@section('title', 'Finding your therapist')
@section('content')
{{-- The lobby while we find a therapist who is free now (redesigned 2026-11-06, Collins:
     "a warm atmosphere ... advising them to have good internet, relax, they are in a good
     space"). Uses the client look: the home page's soft hero panel and plant, white cards.
       - Steps show where they are; "Waiting m:ss" counts from entered_lobby_at.
       - The connection notice is judged from our own status checks (partials/calm-art).
       - Practical tips rotate (relax / connection / privacy); a short get-ready list.
       - The screen is kept on while waiting.
     Polls sessions.status every 5 s; when a therapist is placed it shows their name for a
     moment, then moves on. After lobby_target_minutes, offers "Browse therapists instead". --}}

<style>
    .mwl { max-width:880px; margin:0 auto; display:grid; gap:16px; }
    .mwl-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:16px; align-items:start; }
    @media (max-width: 720px) { .mwl-grid { grid-template-columns:1fr; } }
    .mwl .card { margin:0; min-width:0; }
    .mwl-h2 { font-size:16px; font-weight:800; color:var(--ink-900); margin:0 0 12px; letter-spacing:-.01em; display:flex; justify-content:space-between; align-items:baseline; gap:10px; }
    .mwl-h2 small { font-size:12.5px; font-weight:600; color:var(--ink-500); font-variant-numeric:tabular-nums; }

    .mwl-steps { list-style:none; margin:0; padding:0; }
    .mwl-steps li { display:flex; gap:12px; align-items:flex-start; position:relative; padding-bottom:14px; }
    .mwl-steps li:last-child { padding-bottom:0; }
    .mwl-steps li:not(:last-child)::after { content:''; position:absolute; left:11px; top:26px; bottom:2px; width:2px; background:rgba(3,40,80,.08); }
    .mwl-ic { width:24px; height:24px; flex:0 0 24px; border-radius:50%; border:2px solid #A3AFC0; display:grid; place-items:center; background:#fff; color:#fff; }
    .mwl-ic svg { width:13px; height:13px; display:none; }
    .mwl-t { font-weight:700; color:var(--ink-500); font-size:14px; }
    .mwl-s { font-size:12.5px; color:var(--ink-500); }
    .mwl-steps .done .mwl-ic { background:var(--mw-green-500, #00B888); border-color:var(--mw-green-500, #00B888); }
    .mwl-steps .done .mwl-ic svg { display:block; }
    .mwl-steps .done .mwl-t { color:var(--ink-900); }
    .mwl-steps .now .mwl-ic { border-color:var(--mw-navy, #034C8F); }
    .mwl-steps .now .mwl-ic::after { content:''; width:8px; height:8px; border-radius:50%; background:var(--mw-navy, #034C8F); animation:mwlPing 1.8s ease-out infinite; }
    .mwl-steps .now .mwl-t { color:var(--mw-navy, #034C8F); }
    @keyframes mwlPing { 0% { box-shadow:0 0 0 0 rgba(3,76,143,.45); } 100% { box-shadow:0 0 0 8px rgba(3,76,143,0); } }

    .mwl-net { margin:14px 0 0; }

    .mwl-tip-k { font-size:12px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--mw-green, #00A87C); }
    .mwl-tip { font-size:16px; font-weight:600; color:var(--ink-900); line-height:1.5; margin:6px 0 14px; min-height:4.5em; transition:opacity .5s ease; }
    .mwl-tip.out { opacity:0; }
    .mwl-tip-foot { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .mwl-dots { display:flex; gap:5px; }
    .mwl-dots i { width:6px; height:6px; border-radius:3px; background:#A3AFC0; opacity:.5; transition:all .3s; }
    .mwl-dots i.on { width:16px; background:var(--mw-navy, #034C8F); opacity:1; }
    .mwl-link { border:0; background:none; color:var(--mw-navy, #034C8F); font-weight:700; font-size:13px; cursor:pointer; padding:6px 0; font-family:inherit; }

    .mwl-ticks { list-style:none; margin:0; padding:0; display:grid; gap:8px; }
    .mwl-tick { width:100%; display:flex; align-items:center; gap:12px; text-align:left; border:0; border-radius:14px; padding:11px 12px; background:#F7F9FC; color:var(--ink-700); font:inherit; font-size:13.5px; font-weight:600; cursor:pointer; min-height:46px; transition:background .2s; }
    .mwl-tick:hover { background:var(--mw-navy-tint, #E6EFF9); }
    .mwl-bx { width:22px; height:22px; flex:0 0 22px; border-radius:7px; border:2px solid #A3AFC0; display:grid; place-items:center; background:#fff; transition:all .2s; }
    .mwl-bx svg { width:12px; height:12px; color:#fff; opacity:0; transform:scale(.5); transition:all .25s cubic-bezier(.3,1.6,.5,1); }
    .mwl-tick[aria-pressed="true"] { color:var(--ink-900); background:var(--mw-green-tint, #DDF5EC); }
    .mwl-tick[aria-pressed="true"] .mwl-bx { background:var(--mw-green-500, #00B888); border-color:var(--mw-green-500, #00B888); }
    .mwl-tick[aria-pressed="true"] .mwl-bx svg { opacity:1; transform:scale(1); }
    .mwl-done { font-size:13px; font-weight:700; color:var(--mw-green, #00A87C); margin-top:10px; }
    .mwl-done:empty { display:none; }

    .mwl-later { border-top:1px solid rgba(3,40,80,.08); margin-top:14px; padding-top:14px; }
    .mwl-later p { margin:0 0 10px; font-size:13px; color:var(--ink-700); }
    .mwl-foot { text-align:center; font-size:12px; color:var(--ink-500); margin:0; }
    @media (prefers-reduced-motion: reduce) { .mwl-steps .now .mwl-ic::after { animation:none; } .mwl-tip { transition:none; } }
</style>

@php($mwlTick = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>')

<div class="mwl">
    <section class="mwcalm-hero">
        <div class="mwcalm-text">
            <div class="mwcalm-kicker" id="mwl-kicker">Finding your therapist</div>
            <h1 id="mwl-title">Take a breath. You're in the right place.</h1>
            <p id="mwl-sub">We're finding a therapist who is free now. Keep this page open and we'll bring you in as soon as they're ready.</p>
        </div>
        @include('partials.calm-art')
    </section>

    <div class="mwl-grid">
        <section class="card" aria-labelledby="mwl-where">
            <h2 class="mwl-h2" id="mwl-where">Where you are <small id="mwl-waited">Waiting 0:00</small></h2>
            <ol class="mwl-steps">
                <li class="done"><span class="mwl-ic">{!! $mwlTick !!}</span><div><div class="mwl-t">Session booked</div></div></li>
                <li class="now" id="mwl-s-match"><span class="mwl-ic">{!! $mwlTick !!}</span><div><div class="mwl-t">Finding a therapist</div><div class="mwl-s" id="mwl-s-match-s">Most people are matched in under {{ $targetMinutes }} minutes</div></div></li>
                <li id="mwl-s-join"><span class="mwl-ic">{!! $mwlTick !!}</span><div><div class="mwl-t">Joining your session</div></div></li>
            </ol>

            <div class="mw-note top info mwl-net" id="mwl-net" role="status">
                <div class="mw-note-body">
                    <div class="mw-note-title" id="mwl-net-t">Checking your connection…</div>
                    <div class="mw-note-text" id="mwl-net-x" hidden></div>
                </div>
            </div>

            <div class="mwl-later" id="mwl-later" hidden>
                <p>This is taking longer than usual. You can keep waiting while we keep looking, or choose a therapist and a time yourself.</p>
                <form method="POST" action="{{ route('sessions.cancel', $session) }}">
                    @csrf
                    <button type="submit" class="btn ghost" style="border-radius:999px;">Browse therapists instead</button>
                </form>
            </div>
        </section>

        <section class="card" aria-labelledby="mwl-wait">
            <h2 class="mwl-h2" id="mwl-wait">While you wait</h2>
            <div class="mwl-tip-k" id="mwl-tip-k">Relax</div>
            <p class="mwl-tip" id="mwl-tip" aria-live="polite">Take a slow breath in, and a longer breath out. There is no rush.</p>
            <div class="mwl-tip-foot">
                <div class="mwl-dots" id="mwl-dots" aria-hidden="true"></div>
                <button type="button" class="mwl-link" id="mwl-tip-next">Next tip</button>
            </div>
        </section>
    </div>

    <section class="card" aria-labelledby="mwl-ready">
        <h2 class="mwl-h2" id="mwl-ready">Get ready <small id="mwl-count">0 of 4 done</small></h2>
        <ul class="mwl-ticks" id="mwl-ticks">
            @foreach (["I'm somewhere quiet and private", 'My headphones are in, if I have them', 'My phone is charged or plugged in', 'I have some water nearby'] as $item)
                <li><button type="button" class="mwl-tick" aria-pressed="false"><span class="mwl-bx">{!! $mwlTick !!}</span>{{ $item }}</button></li>
            @endforeach
        </ul>
        <div class="mwl-done" id="mwl-done" aria-live="polite"></div>
    </section>

    <p class="mwl-foot">This page updates by itself. You don't need to refresh it.</p>
</div>

<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var statusUrl = @json(route('sessions.status', $session));
    var target = {{ (int) $targetMinutes }} * 60;
    var waited = {{ (int) $waitedSeconds }};
    var found = false;
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    MwCalm.keepAwake();

    // ---- Waiting time and the "browse instead" option
    function fmt(s) { var m = Math.floor(s / 60), r = s % 60; return m + ':' + (r < 10 ? '0' : '') + r; }
    function renderWait() {
        if (found) return;
        $('mwl-waited').textContent = 'Waiting ' + fmt(waited);
        $('mwl-later').hidden = waited < target;
    }
    renderWait();
    setInterval(function () { waited++; renderWait(); }, 1000);

    // ---- Connection notice
    var NET = {
        checking: ['Checking your connection…', '', 'info'],
        good: ['Your connection looks good', '', 'success'],
        weak: ['Your connection is weak', 'Move to where your signal is strongest, or closer to your Wi-Fi. Pause any downloads.', 'warn'],
        off: ["You're offline", "We'll keep your place. When you're back online, this page carries on by itself.", 'danger']
    };
    MwCalm.onNet(function (q) {
        var box = $('mwl-net'), n = NET[q];
        box.className = 'mw-note top mwl-net ' + n[2];
        $('mwl-net-t').textContent = n[0];
        $('mwl-net-x').textContent = n[1];
        $('mwl-net-x').hidden = !n[1];
    });

    // ---- Is a therapist free yet?
    function found_(data) {
        found = true;
        var m = $('mwl-s-match'); m.className = 'done';
        $('mwl-s-match-s').textContent = data.therapist || '';
        $('mwl-s-join').className = 'now';
        $('mwl-kicker').textContent = 'Therapist found';
        $('mwl-title').textContent = data.therapist ? data.therapist + ' is ready for you' : 'Your therapist is ready for you';
        $('mwl-sub').textContent = 'Taking you to your session now…';
        $('mwl-waited').textContent = 'Matched in ' + fmt(waited);
        $('mwl-later').hidden = true;
        setTimeout(function () { window.location.href = data.redirect; }, reduce ? 300 : 1800);
    }
    function poll() {
        MwCalm.fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.redirect) {
                    if (data.status === 'scheduled') { found_(data); } else { window.location.href = data.redirect; }
                    return;
                }
                setTimeout(poll, 5000);
            })
            .catch(function () { setTimeout(poll, 8000); });
    }
    setTimeout(poll, 3000);

    // ---- Tips: short and practical (relax, connection, privacy)
    var tips = [
        ['Relax', 'Take a slow breath in, and a longer breath out. There is no rush.'],
        ['Your connection', 'Stay where your signal is strongest. On Wi-Fi, sit close to the router.'],
        ['Privacy', 'Your session is private. Only you and your therapist will be there.'],
        ['Your connection', 'Pause downloads and close video apps so your call stays clear.'],
        ['Relax', 'Let your shoulders drop and rest your feet on the floor.'],
        ['Getting started', "You don't need to prepare anything. Start wherever feels easiest."],
        ['Your connection', 'Headphones help you hear clearly and keep the call private.']
    ];
    var ti = 0, dots = $('mwl-dots');
    tips.forEach(function () { dots.appendChild(document.createElement('i')); });
    function showTip() {
        var t = tips[ti % tips.length], el = $('mwl-tip');
        el.classList.add('out');
        setTimeout(function () {
            $('mwl-tip-k').textContent = t[0]; el.textContent = t[1]; el.classList.remove('out');
            [].forEach.call(dots.children, function (d, i) { d.classList.toggle('on', i === ti % tips.length); });
        }, reduce ? 0 : 300);
    }
    var tipTimer = setInterval(function () { ti++; showTip(); }, 8000);
    $('mwl-tip-next').addEventListener('click', function () {
        clearInterval(tipTimer); ti++; showTip();
        tipTimer = setInterval(function () { ti++; showTip(); }, 8000);
    });
    showTip();

    // ---- Get-ready list (just for the client; nothing is saved)
    var list = $('mwl-ticks');
    list.addEventListener('click', function (e) {
        var b = e.target.closest('.mwl-tick'); if (!b) return;
        b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
        var all = list.querySelectorAll('.mwl-tick').length, on = list.querySelectorAll('[aria-pressed="true"]').length;
        $('mwl-count').textContent = on + ' of ' + all + ' done';
        $('mwl-done').textContent = on === all ? "You're all set. Your therapist will be with you soon." : '';
    });
})();
</script>
@endsection
