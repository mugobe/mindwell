{{-- Calm waiting art (2026-11-06): the plant from the client home page, with soft circles
     that breathe (4 s in, 6 s out), a "Breathe in / out" label and a few leaves drifting down
     inside the hero panel. Used by the "finding a therapist" lobby and the video waiting room.

     Put it inside an element with class "mwcalm-hero" (position:relative; overflow:hidden).
     Options: 'leaves' => bool (default true), 'breath' => bool (default true).

     Also defines window.MwCalm (once per page):
       MwCalm.fetch(url, opts)  fetch() that also measures the connection
       MwCalm.onNet(fn)         fn('checking'|'good'|'weak'|'off') whenever it changes
       MwCalm.keepAwake()       asks the phone not to dim the screen while waiting
     All motion stops for people who ask their device for reduced motion. --}}
@php($mwcLeaves = $leaves ?? true)
@php($mwcBreath = $breath ?? true)

@once
<style>
    .mwcalm-hero { position:relative; overflow:hidden; display:flex; align-items:center; gap:24px; padding:28px 32px; border-radius:26px;
        background:linear-gradient(120deg, #EAF2FC 0%, #EEF8F4 55%, #FFF4E6 100%); }
    .mwcalm-text { position:relative; z-index:2; flex:1; min-width:0; }
    .mwcalm-kicker { font-size:12px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--mw-green, #00A87C); }
    .mwcalm-hero h1, .mwcalm-hero .mwcalm-h1 { font-size:30px; line-height:1.15; font-weight:800; letter-spacing:-.025em; color:var(--mw-navy-900, #022F5C); margin:6px 0 8px; text-wrap:balance; }
    .mwcalm-hero p { font-size:15px; color:var(--ink-700, #3A4A63); margin:0; max-width:480px; line-height:1.55; }
    .mwcalm-art { position:relative; z-index:2; flex:0 0 200px; width:200px; display:flex; flex-direction:column; align-items:center; }
    .mwcalm-art svg { width:200px; height:155px; display:block; }
    .mwcalm-art .halo { transform-box:fill-box; transform-origin:center; animation:mwcalmBreathe 10s ease-in-out infinite; }
    .mwcalm-art .halo.b { animation-delay:-1.5s; }
    .mwcalm-art .sway { animation:mwcalmSway 5s ease-in-out infinite; transform-origin:110px 150px; }
    .mwcalm-breath { font-size:13px; font-weight:700; color:var(--mw-navy, #034C8F); margin-top:2px; min-height:20px; }
    @keyframes mwcalmBreathe { 0% { transform:scale(.86); } 40% { transform:scale(1.08); } 100% { transform:scale(.86); } }
    @keyframes mwcalmSway { 0%, 100% { transform:rotate(-2deg); } 50% { transform:rotate(2deg); } }

    .mwcalm-leaves { position:absolute; inset:0 0 0 52%; z-index:1; pointer-events:none; }
    .mwcalm-lf { position:absolute; top:-30px; opacity:0; animation:mwcalmFall linear infinite; }
    .mwcalm-lf.b { animation-name:mwcalmFall2; }
    .mwcalm-lf svg { width:100%; height:100%; display:block; }
    @keyframes mwcalmFall { 0% { transform:translate(0,0) rotate(0); opacity:0; } 10% { opacity:.8; } 50% { transform:translate(26px,130px) rotate(140deg); } 90% { opacity:.7; } 100% { transform:translate(-8px,290px) rotate(300deg); opacity:0; } }
    @keyframes mwcalmFall2 { 0% { transform:translate(0,0) rotate(20deg); opacity:0; } 10% { opacity:.75; } 50% { transform:translate(-24px,140px) rotate(-120deg); } 90% { opacity:.65; } 100% { transform:translate(10px,300px) rotate(-260deg); opacity:0; } }

    @media (max-width: 720px) {
        .mwcalm-hero { flex-direction:column-reverse; align-items:stretch; padding:20px; gap:6px; }
        .mwcalm-art { flex:none; width:100%; }
        .mwcalm-art svg { width:170px; height:132px; }
        .mwcalm-leaves { left:0; bottom:auto; height:190px; overflow:hidden; } /* leaves stay above the text on phones */
        .mwcalm-hero h1, .mwcalm-hero .mwcalm-h1 { font-size:24px; }
        .mwcalm-hero p { font-size:14px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .mwcalm-art .halo, .mwcalm-art .sway { animation:none !important; }
        .mwcalm-leaves { display:none; }
    }
</style>
<script>
window.MwCalm = window.MwCalm || (function () {
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---- Connection: judged from how long our own status checks take, plus what the
    // phone reports (Chrome on Android). No extra downloads, so no extra data used.
    var times = [], fails = 0, quality = 'checking', listeners = [];
    function judge() {
        var q;
        if (!navigator.onLine || fails >= 2) q = 'off';
        else {
            var c = navigator.connection, hint = c && (c.saveData || /2g$/.test(c.effectiveType || '') || (c.downlink && c.downlink < 0.7));
            var sorted = times.slice().sort(function (a, b) { return a - b; }), mid = sorted[Math.floor(sorted.length / 2)];
            q = !times.length ? (hint ? 'weak' : 'checking') : ((hint || mid > 1800) ? 'weak' : 'good');
        }
        if (q !== quality) { quality = q; listeners.forEach(function (fn) { try { fn(q); } catch (e) {} }); }
    }
    function timedFetch(url, opts) {
        var t = performance.now();
        return fetch(url, opts).then(function (r) {
            fails = 0; times.push(performance.now() - t); if (times.length > 3) times.shift(); judge(); return r;
        }, function (e) { fails++; judge(); throw e; });
    }
    addEventListener('online', judge);
    addEventListener('offline', judge);
    if (navigator.connection && navigator.connection.addEventListener) navigator.connection.addEventListener('change', judge);

    // ---- Keep the screen on while waiting, so the phone doesn't sleep and miss the moment.
    var lock = null, wantLock = false;
    function requestLock() {
        if (!wantLock || !('wakeLock' in navigator) || document.visibilityState !== 'visible') return;
        navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {});
    }
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible' && wantLock) requestLock(); });

    // ---- Breathing label and leaves
    function boot() {
        var start = performance.now();
        var labels = document.querySelectorAll('[data-mwcalm-breath]');
        if (labels.length) setInterval(function () {
            var p = ((performance.now() - start) % 10000) / 10000;
            var txt = reduce ? 'Breathe slowly' : (p < 0.4 ? 'Breathe in' : 'Breathe out');
            labels.forEach(function (el) { if (el.textContent !== txt) el.textContent = txt; });
        }, 200);

        if (reduce) return;
        var colours = ['#00B888', '#34C79E', '#00A87C', '#34C79E', '#F88000', '#00B888', '#7FD8BE'];
        document.querySelectorAll('[data-mwcalm-leaves]').forEach(function (box) {
            colours.forEach(function (c, i) {
                var el = document.createElement('span'), s = 11 + (i * 7) % 9;
                el.className = 'mwcalm-lf' + (i % 2 ? ' b' : '');
                el.style.cssText = 'left:' + (4 + i * 13.5) + '%;width:' + s + 'px;height:' + s + 'px;animation-duration:' + (13 + (i * 5) % 8) + 's;animation-delay:' + (-i * 2.3) + 's';
                el.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20C4 10 10 4 20 4C20 14 14 20 4 20Z" fill="' + c + '"/><path d="M5 19L15 9" stroke="#fff" stroke-width="1.2" stroke-linecap="round" opacity=".6"/></svg>';
                box.appendChild(el);
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else setTimeout(boot, 0);

    return {
        fetch: timedFetch,
        onNet: function (fn) { listeners.push(fn); fn(quality); },
        keepAwake: function () { wantLock = true; requestLock(); },
        letSleep: function () { wantLock = false; if (lock) { lock.release().catch(function () {}); lock = null; } }
    };
})();
</script>
@endonce

@if ($mwcLeaves)
    <div class="mwcalm-leaves" aria-hidden="true" data-mwcalm-leaves></div>
@endif
<div class="mwcalm-art" aria-hidden="true">
    <svg viewBox="0 0 220 170">
        <ellipse class="halo" cx="96" cy="96" rx="74" ry="60" fill="#D7E6F7"/>
        <ellipse class="halo b" cx="150" cy="74" rx="44" ry="38" fill="#CDEFE2"/>
        <circle cx="186" cy="24" r="12" fill="#FFD29E"/>
        <g class="sway" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path d="M110 152 C 110 120 108 98 112 70" stroke="#034C8F" stroke-width="3.5"/>
            <path d="M111 104 C 92 104 76 94 72 74 C 92 72 106 84 111 104 Z" fill="#00B888" stroke="#00A87C" stroke-width="2"/>
            <path d="M111 88 C 128 86 142 74 144 54 C 124 54 112 68 111 88 Z" fill="#34C79E" stroke="#00A87C" stroke-width="2"/>
            <path d="M112 70 C 104 58 104 44 114 34 C 122 46 120 60 112 70 Z" fill="#F88000" stroke="#E07200" stroke-width="2"/>
        </g>
        <path d="M80 154 h60" stroke="#034C8F" stroke-width="3.5" stroke-linecap="round" opacity=".25"/>
    </svg>
    @if ($mwcBreath)
        <div class="mwcalm-breath" data-mwcalm-breath>Breathe in</div>
    @endif
</div>
