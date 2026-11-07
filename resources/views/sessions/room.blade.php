@extends('layouts.app')
@section('title', 'Video session')
@section('content')
{{--
    Real video sessions (LiveKit). Built 2026-09-28; early start + alerts the same day;
    REDESIGNED 2026-09-29 (warmer waiting room, camera/mic check before joining, floating
    in-call controls, session timer, leave confirmation, thank-you screen for clients) and
    given a side-panel notepad (client → their private journal, therapist → live notes
    that pre-fill the closure report). See VideoRoomController for who may enter when.

    $access['state'] is 'open', 'too_early' or 'ended' ('closed' never reaches this view).
    Before joining, the page polls sessions.room.status every 5s. A LiveKit token is only
    fetched from sessions.room.token when Join is clicked.

    livekit-client 2.22.3 (UMD, global `LivekitClient`), pinned with SRI. Camera/mic need
    a secure context: localhost/127.0.0.1 is fine, a LAN IP over plain http is not.

    Rule learned 2026-09-28: only ever pass ONE variable to the json directive — Blade splits its
    argument on commas.
--}}
@php
    $startsAt = $access['starts_at'];
    $opensAt = $access['opens_at'];
    $closesAt = $access['closes_at'];
    $selfName = auth()->user()->name;
    $selfFirst = explode(' ', trim($selfName))[0] ?: $selfName;
    $otherInitial = mb_strtoupper(mb_substr($otherName, 0, 1));
    $selfInitial = mb_strtoupper(mb_substr($selfName, 0, 1));
@endphp

<style>
    .mw-room { max-width: 1180px; margin: 4px auto 24px; }
    .mw-room-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
    .mw-room-head h1 { font-size:19px; font-weight:800; color:var(--ink-900); margin:0; }
    .mw-room-sub { font-size:12.5px; color:var(--ink-500); margin-top:2px; }
    .mw-timer { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; background:var(--mw-teal-tint, #DCEDE8); color:var(--ink-900); font-size:12.5px; font-weight:700; }
    .mw-timer.over { background:var(--mw-gold, #F6E1A8); }
    .mw-grid { display:grid; grid-template-columns: minmax(0, 1fr) 320px; gap:18px; align-items:start; }
    @media (max-width: 960px) { .mw-grid { grid-template-columns: 1fr; } }

    /* ---- waiting room (calm redesign 2026-11-06: the client look's soft hero panel and
       plant from partials/calm-art, white cards, connection check, screen kept on) */
    .mw-lobby { display:grid; gap:16px; }
    .mw-avatar { width:78px; height:78px; border-radius:50%; background:var(--mw-teal-tint, #DCEDE8); color:var(--teal-600, #2f6f66); display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:800; position:relative; }
    .mwr-who { display:flex; align-items:center; gap:16px; }
    .mwr-cal { flex:0 0 76px; height:84px; border-radius:18px; background:var(--mw-navy, #034C8F); color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(3,76,143,.25); }
    .mwr-cal .m { font-size:11px; font-weight:800; letter-spacing:.1em; opacity:.85; }
    .mwr-cal .d { font-size:22px; font-weight:800; line-height:1.1; margin:2px 0; }
    .mwr-cal .w { font-size:11px; font-weight:700; opacity:.85; }
    .mwr-state { display:flex; align-items:center; gap:10px; margin-top:14px; padding:10px 14px; border-radius:16px; background:#fff; box-shadow:0 1px 2px rgba(3,40,80,.05), 0 0 0 1px rgba(3,40,80,.05); max-width:520px; }
    .mwr-state .dot { width:9px; height:9px; flex:0 0 9px; border-radius:50%; background:#A3AFC0; }
    .mwr-state .txt { font-size:13.5px; font-weight:600; color:var(--ink-900); line-height:1.45; flex:1; min-width:0; }
    .mwr-state .cd { font-size:13px; font-weight:800; color:var(--mw-navy, #034C8F); font-variant-numeric:tabular-nums; white-space:nowrap; }
    .mw-open .mwr-state .dot, .mw-ready .mwr-state .dot { background:var(--mw-green-500, #00B888); animation:mwrPing 1.8s ease-out infinite; }
    .mw-ready .mwr-state { background:var(--mw-green-tint, #DDF5EC); }
    @keyframes mwrPing { 0% { box-shadow:0 0 0 0 rgba(0,184,136,.5); } 100% { box-shadow:0 0 0 10px rgba(0,184,136,0); } }

    .mwr-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:16px; align-items:start; }
    @media (max-width: 720px) { .mwr-row { grid-template-columns:1fr; } }
    .mwr-row .card { margin:0; min-width:0; }
    .mwr-h { font-size:16px; font-weight:800; color:var(--ink-900); margin:0 0 12px; letter-spacing:-.01em; }
    .mwr-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
    .mwr-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 11px; border-radius:999px; background:#F7F9FC; color:var(--ink-500); font-size:12px; font-weight:700; }
    .mwr-chip::before { content:''; width:7px; height:7px; border-radius:50%; background:#A3AFC0; }
    .mwr-chip.ok { background:var(--mw-green-tint, #DDF5EC); color:#0E7A5A; } .mwr-chip.ok::before { background:var(--mw-green-500, #00B888); }
    .mwr-chip.warn { background:#FBF0D9; color:#9A5B00; } .mwr-chip.warn::before { background:#B06000; }
    .mwr-chip.bad { background:#FDE7DC; color:#B3261E; } .mwr-chip.bad::before { background:#B3261E; }
    .mwr-ticks { list-style:none; margin:0; padding:0; display:grid; gap:8px; }
    .mwr-tick { width:100%; display:flex; align-items:center; gap:12px; text-align:left; border:0; border-radius:14px; padding:10px 12px; background:#F7F9FC; color:var(--ink-700); font:inherit; font-size:13.5px; font-weight:600; cursor:pointer; min-height:44px; transition:background .2s; }
    .mwr-tick:hover { background:var(--mw-navy-tint, #E6EFF9); }
    .mwr-bx { width:22px; height:22px; flex:0 0 22px; border-radius:7px; border:2px solid #A3AFC0; display:grid; place-items:center; background:#fff; transition:all .2s; }
    .mwr-bx svg { width:12px; height:12px; color:#fff; opacity:0; transform:scale(.5); transition:all .25s cubic-bezier(.3,1.6,.5,1); }
    .mwr-tick[aria-pressed="true"] { color:var(--ink-900); background:var(--mw-green-tint, #DDF5EC); }
    .mwr-tick[aria-pressed="true"] .mwr-bx { background:var(--mw-green-500, #00B888); border-color:var(--mw-green-500, #00B888); }
    .mwr-tick[aria-pressed="true"] .mwr-bx svg { opacity:1; transform:scale(1); }
    .mwr-tip { font-size:12.5px; color:var(--ink-500); margin:12px 0 0; line-height:1.5; }
    .mwr-join { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:center; text-align:center; }
    .mwr-join .btn { border-radius:999px; padding:12px 30px; font-size:15px; }
    .mwr-join .mw-status, .mwr-join .mw-warn { width:100%; }
    .mwr-join #mw-join.mwr-glow { animation:mwrGlow 2s ease-out infinite; }
    @keyframes mwrGlow { 0% { box-shadow:0 0 0 0 rgba(238,120,0,.45); } 100% { box-shadow:0 0 0 12px rgba(238,120,0,0); } }
    .mwr-awake { width:100%; font-size:11.5px; color:var(--ink-500); margin:0; }

    .mw-preview { position:relative; aspect-ratio:16/10; border-radius:12px; overflow:hidden; background:#1F2D3D; display:flex; align-items:center; justify-content:center; color:#d7e6e2; font-size:12.5px; text-align:center; padding:0; }
    .mw-preview video { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; transform:scaleX(-1); }
    .mw-meter { height:6px; border-radius:3px; background:rgba(0,0,0,.08); overflow:hidden; margin-top:10px; }
    .mw-meter i { display:block; height:100%; width:0%; background:#6fc3a7; transition:width .08s linear; }
    .mw-devices { display:flex; flex-direction:column; gap:6px; margin-top:10px; }
    .mw-devices select { font-size:12px; padding:6px 8px; border-radius:8px; border:1px solid rgba(0,0,0,.12); background:#fff; width:100%; }
    .mw-warn { background:#fff4e5; border:1px solid #f3d3a3; color:#7a4b10; border-radius:12px; padding:10px 12px; font-size:12.5px; text-align:left; margin-top:14px; }
    .mw-status { text-align:center; font-size:12.5px; color:var(--ink-500); margin-top:10px; min-height:18px; }

    /* ---- in-call stage */
    .mw-stage { position:relative; background:#1f2e2b; border-radius:22px; overflow:hidden; aspect-ratio:16/10; }
    .mw-stage video { width:100%; height:100%; object-fit:cover; display:block; }
    .mw-remote { position:absolute; inset:0; }
    .mw-stage-placeholder { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; color:#d7e6e2; text-align:center; padding:24px; font-size:14px; }
    .mw-stage-placeholder .mw-avatar { background:rgba(255,255,255,.1); color:#fff; }
    .mw-tag { position:absolute; left:14px; top:14px; padding:4px 10px; border-radius:999px; background:rgba(20,30,28,.6); color:#fff; font-size:12px; font-weight:600; backdrop-filter:blur(6px); }
    .mw-pip { position:absolute; right:14px; top:14px; width:24%; max-width:220px; aspect-ratio:4/3; border-radius:14px; overflow:hidden; background:#2c3f3b; box-shadow:0 6px 18px rgba(0,0,0,.35); border:2px solid rgba(255,255,255,.18); }
    .mw-pip video { transform:scaleX(-1); }
    .mw-pip-off { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#fff; font-size:22px; font-weight:800; }
    .mw-pip-badges { position:absolute; left:6px; bottom:6px; display:flex; gap:4px; }
    .mw-chip { padding:2px 7px; border-radius:999px; background:rgba(20,30,28,.7); color:#fff; font-size:10.5px; font-weight:700; }
    .mw-chip.off { background:#c9574d; }
    .mw-bar { position:absolute; left:50%; bottom:16px; transform:translateX(-50%); display:flex; gap:12px; padding:8px 12px; border-radius:999px; background:rgba(20,30,28,.62); backdrop-filter:blur(8px); }
    .mw-round { width:50px; height:50px; border-radius:50%; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.14); color:#fff; transition:background .15s, transform .1s; }
    .mw-round:hover { background:rgba(255,255,255,.24); }
    .mw-round:active { transform:scale(.95); }
    .mw-round.off { background:#c9574d; }
    .mw-round.leave { background:var(--coral-500, #e8825e); }
    .mw-round svg { width:22px; height:22px; }
    @media (max-width: 640px) { .mw-stage { aspect-ratio: 3/4; } .mw-pip { width:34%; } }

    /* ---- notes + thank-you + modal */
    .mw-notes { position:sticky; top:14px; padding:18px; display:flex; flex-direction:column; }
    .mw-notes textarea { min-height:280px; resize:vertical; font-size:13.5px; line-height:1.5; }
    .mw-notes-foot { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:10px; font-size:12px; color:var(--ink-500); flex-wrap:wrap; }
    @media (max-width: 960px) { .mw-notes { position:static; } .mw-notes textarea { min-height:160px; } }
    .mw-thanks { border-radius:22px; padding:40px 28px; text-align:center; background:
        radial-gradient(ellipse 36% 30% at 10% 15%, var(--mw-blush, #F5DCE2) 0 60%, transparent 62%),
        radial-gradient(ellipse 30% 28% at 92% 85%, var(--mw-sage, #DEE8CE) 0 60%, transparent 62%), #fffdf8; }
    /* session feedback on the thank-you screen (2026-10-04) */
    .mw-rate { max-width:420px; margin:0 auto 22px; padding:18px; border-radius:16px; background:#fff; border:1px solid rgba(0,0,0,.06); text-align:center; }
    .mw-stars { display:inline-flex; flex-direction:row-reverse; gap:4px; margin:8px 0 4px; }
    .mw-stars input { position:absolute; opacity:0; width:0; height:0; }
    .mw-stars label { font-size:32px; line-height:1; color:#d8dedc; cursor:pointer; padding:2px; transition:color .1s, transform .1s; }
    .mw-stars label:hover, .mw-stars label:hover ~ label, .mw-stars input:checked ~ label { color:#e8a33d; }
    .mw-stars input:focus-visible + label { outline:2px solid var(--teal-600, #2f6f66); outline-offset:2px; border-radius:6px; }
    .mw-stars label:active { transform:scale(.9); }
    .mw-rate textarea { width:100%; box-sizing:border-box; min-height:64px; margin-top:8px; font-size:13px; }
    .mw-modal { position:fixed; inset:0; background:rgba(20,30,28,.45); display:flex; align-items:center; justify-content:center; z-index:50; padding:16px; }
    .mw-modal-card { background:#fff; border-radius:18px; padding:24px; max-width:360px; width:100%; text-align:center; box-shadow:0 20px 50px rgba(0,0,0,.25); }
    .mw-hidden { display:none !important; }
    .btn[disabled] { opacity:.5; cursor:not-allowed; }
    @media (prefers-reduced-motion: reduce) { .mw-open .mwr-state .dot, .mw-ready .mwr-state .dot, .mwr-join #mw-join.mwr-glow { animation:none !important; } }
</style>

<div class="mw-room">
    <div class="mw-room-head">
        <div>
            <h1>Session with {{ $otherName }}</h1>
            <div class="mw-room-sub">{{ $startsAt->format('l, M j · g:i A') }} · {{ $session->duration_minutes }} minutes</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="mw-timer mw-hidden" id="mw-timer"></span>
            <a href="{{ $backUrl }}" class="btn ghost sm" style="border-radius:999px;">Back</a>
        </div>
    </div>

    @if (! $configured)
        <div class="card" style="text-align:center;padding:32px 24px;">
            @include('partials.mw-icon-badge', ['tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/>'])
            <h2 style="font-size:16px;font-weight:800;color:var(--ink-900);margin-bottom:6px;">Video isn't set up yet</h2>
            <p style="font-size:13px;color:var(--ink-500);margin:0;">
                The video service hasn't been configured on this server. Add <code>LIVEKIT_URL</code>,
                <code>LIVEKIT_API_KEY</code> and <code>LIVEKIT_API_SECRET</code> to <code>.env</code>.
            </p>
        </div>
    @elseif ($access['state'] === 'ended')
        <div class="card" style="text-align:center;padding:32px 24px;">
            @include('partials.mw-icon-badge', ['tint' => 'var(--mw-blush, #F3D9D2)', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>'])
            <h2 style="font-size:16px;font-weight:800;color:var(--ink-900);margin-bottom:6px;">This session's video window has ended</h2>
            <p style="font-size:13px;color:var(--ink-500);margin:0;">
                The room closed at {{ $closesAt->format('g:i A') }}.
                @if ($role === 'therapist')
                    You can still <a href="{{ route('therapist.sessions.close', $session) }}" style="color:var(--teal-600);">close out the session</a>.
                @else
                    Any notes you took are in <a href="{{ route('journal.index') }}" style="color:var(--teal-600);">your journal</a>.
                @endif
            </p>
        </div>
    @else
        <div class="mw-grid">
            <div>
                {{-- ================= WAITING ROOM (before joining) ================= --}}
                <div class="mw-lobby" id="mw-lobby">
                    <section class="mwcalm-hero" id="mw-who">
                        <div class="mwcalm-text">
                            <div class="mwr-who">
                                <div class="mwr-cal" aria-hidden="true">
                                    <span class="m">{{ $startsAt->isToday() ? 'TODAY' : strtoupper($startsAt->format('D j')) }}</span>
                                    <span class="d">{{ $startsAt->format('g:i') }}</span>
                                    <span class="w">{{ $startsAt->format('A') }}</span>
                                </div>
                                <div style="min-width:0;">
                                    <div class="mwcalm-kicker">Video session, {{ $session->duration_minutes }} minutes</div>
                                    <div class="mwcalm-h1" id="mw-who-head" style="font-size:24px;margin:4px 0 2px;"></div>
                                </div>
                            </div>
                            <div class="mwr-state" role="status">
                                <span class="dot" aria-hidden="true"></span>
                                <span class="txt" id="mw-who-text"></span>
                                <span class="cd" id="mw-countdown" data-starts-in="{{ (int) max(0, round(now()->diffInSeconds($startsAt, false))) }}" hidden></span>
                            </div>
                        </div>
                        @include('partials.calm-art', ['leaves' => $role === 'client', 'breath' => $role === 'client'])
                    </section>

                    <div class="mwr-row">
                        <section class="card" aria-labelledby="mw-av-h">
                            <h2 class="mwr-h" id="mw-av-h">Check your camera and microphone</h2>
                            <div class="mw-preview" id="mw-preview">
                                <span id="mw-preview-hint" style="padding:12px;">See how you look and sound before you join.</span>
                            </div>
                            <div class="mw-meter" title="Microphone level"><i id="mw-meter"></i></div>
                            <div class="mw-devices mw-hidden" id="mw-devices">
                                <select id="mw-cam-select" aria-label="Camera"></select>
                                <select id="mw-mic-select" aria-label="Microphone"></select>
                            </div>
                            <div class="mwr-chips" aria-live="polite">
                                <span class="mwr-chip" id="mw-chk-cam">Camera</span>
                                <span class="mwr-chip" id="mw-chk-mic">Microphone</span>
                                <span class="mwr-chip" id="mw-chk-net">Checking connection…</span>
                            </div>
                            <button type="button" class="btn ghost sm" id="mw-preview-btn" style="margin-top:12px;border-radius:999px;width:100%;">Check camera and mic</button>
                        </section>

                        <section class="card" aria-labelledby="mw-bs-h">
                            <h2 class="mwr-h" id="mw-bs-h">Before you start</h2>
                            <ul class="mwr-ticks" id="mw-ticks">
                                @foreach ($role === 'client'
                                    ? ["Somewhere private where I won't be overheard", 'Headphones in, if I have them', 'Water and a tissue nearby']
                                    : ['Private room, door closed', 'Notifications silenced', 'Notes panel open on the right'] as $item)
                                    <li><button type="button" class="mwr-tick" aria-pressed="false"><span class="mwr-bx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></span>{{ $item }}</button></li>
                                @endforeach
                            </ul>
                            <p class="mwr-tip">
                                @if ($role === 'client')
                                    It's okay to feel nervous. You can go at your own pace, and you don't need to prepare anything.
                                    If you'd like, write what you want to talk about in your notes.
                                @else
                                    Your notes save as you type and pre-fill the report. {{ $otherName }} gets a chime the moment you join.
                                @endif
                            </p>
                        </section>
                    </div>

                    <section class="card mwr-join">
                        <div class="mw-warn mw-hidden" id="mw-warn-lobby"></div>
                        <button type="button" class="btn coral" id="mw-join">Join video session</button>
                        <button type="button" class="btn ghost" id="mw-join-audio" hidden>Join with audio only</button>
                        <div class="mw-status" id="mw-status"></div>
                        <p class="mwr-awake">Your screen stays on while you wait, so you won't miss the chime.</p>
                    </section>
                </div>

                {{-- ================= IN CALL ================= --}}
                <div class="mw-hidden" id="mw-call">
                    <div class="mw-stage">
                        <div class="mw-remote" id="mw-remote"></div>
                        <div class="mw-stage-placeholder" id="mw-stage-placeholder">
                            <div class="mw-avatar">{{ $otherInitial }}</div>
                            <div id="mw-stage-text"></div>
                        </div>
                        <span class="mw-tag">{{ $otherName }}</span>

                        <div class="mw-pip" id="mw-pip">
                            <div class="mw-pip-off" id="mw-pip-off">{{ $selfInitial }}</div>
                            <div id="mw-pip-video" style="position:absolute;inset:0;"></div>
                            <div class="mw-pip-badges">
                                <span class="mw-chip">You</span>
                                <span class="mw-chip off mw-hidden" id="mw-chip-mic">Muted</span>
                                <span class="mw-chip off mw-hidden" id="mw-chip-cam">Camera off</span>
                            </div>
                        </div>

                        <div class="mw-bar">
                            <button type="button" class="mw-round" id="mw-mic" aria-label="Mute microphone" title="Mute microphone">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0014 0M12 18v3"/><path class="mw-slash mw-hidden" d="M4 4l16 16"/></svg>
                            </button>
                            <button type="button" class="mw-round" id="mw-cam" aria-label="Turn camera off" title="Turn camera off">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/><path class="mw-slash mw-hidden" d="M3 3l18 18"/></svg>
                            </button>
                            <button type="button" class="mw-round leave" id="mw-leave" aria-label="Leave session" title="Leave session">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14.5c5-4.7 13-4.7 18 0l-2.3 2.6-3.6-1.4v-2.6a11 11 0 00-6.2 0v2.6l-3.6 1.4z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="mw-warn mw-hidden" id="mw-warn-call"></div>
                    <div class="mw-status" id="mw-call-status"></div>
                </div>

                {{-- ================= THANK YOU (client, after leaving) ================= --}}
                <div class="mw-thanks mw-hidden" id="mw-thanks">
                    @include('partials.mw-illustration', ['variant' => 'sprout', 'size' => 120])
                    <h2 style="font-size:20px;font-weight:800;color:var(--ink-900);margin:10px 0 6px;">Thank you for today, {{ $selfFirst }}.</h2>
                    <p style="font-size:13.5px;color:var(--ink-500);max-width:440px;margin:0 auto 20px;line-height:1.55;">
                        Take a moment before you go back to your day. A glass of water and a few slow breaths can help.
                        Your notes from this session are saved in your journal.
                    </p>
                    @if (! empty($jsConfig['canRate']))
                        {{-- Session feedback (2026-10-04) — see SessionRatingController. --}}
                        <form class="mw-rate" id="mw-rate" novalidate>
                            <div style="font-size:14px;font-weight:800;color:var(--ink-900);">How was today's session?</div>
                            <div class="mw-stars" role="radiogroup" aria-label="Rate this session from 1 to 5 stars">
                                @for ($star = 5; $star >= 1; $star--)
                                    <input type="radio" name="rating" id="mw-star-{{ $star }}" value="{{ $star }}">
                                    <label for="mw-star-{{ $star }}" title="{{ $star }} of 5" aria-label="{{ $star }} of 5 stars">★</label>
                                @endfor
                            </div>
                            <textarea class="mw-field" name="comment" maxlength="2000" placeholder="Anything you'd like to tell us? (optional)"></textarea>
                            <div style="font-size:11px;color:var(--ink-500);margin-top:4px;">Only the Mindwell team reads comments. Your therapist does not see them.</div>
                            <div id="mw-rate-error" class="mw-hidden" style="font-size:12px;color:var(--danger, #c0392b);margin-top:6px;"></div>
                            <div style="display:flex;gap:8px;justify-content:center;margin-top:12px;">
                                <button type="submit" class="btn primary sm" style="border-radius:999px;padding-left:20px;padding-right:20px;">Send feedback</button>
                                <button type="button" class="btn ghost sm" id="mw-rate-skip" style="border-radius:999px;">Skip</button>
                            </div>
                        </form>
                        <div class="mw-rate mw-hidden" id="mw-rate-done" style="font-size:13px;color:var(--ink-700);">Thank you for your feedback. It helps us improve.</div>
                    @endif
                    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                        <a href="{{ route('journal.index') }}" class="btn coral" style="border-radius:999px;">Open my journal</a>
                        <a href="{{ route('sessions.history') }}" class="btn ghost" style="border-radius:999px;">Back to my sessions</a>
                    </div>
                </div>
            </div>

            {{-- ================= NOTES ================= --}}
            <div class="card mw-notes">
                <div style="font-size:15px;font-weight:800;color:var(--ink-900);">{{ $role === 'client' ? 'Your notes' : 'Session notes' }}</div>
                <div style="font-size:11.5px;color:var(--ink-500);margin:2px 0 10px;line-height:1.45;">
                    @if ($role === 'client')
                        Before you join, write what you'd like to talk about. During the session, add thoughts or things to try. Saved to your journal and private unless you share them.
                    @else
                        Private to you. Saved as you type, and pre-filled into your report when you close the session.
                    @endif
                </div>
                <textarea class="mw-field" id="mw-notes" placeholder="{{ $role === 'client' ? 'What would you like to talk about today?' : 'Presenting concerns, key moments, interventions…' }}">{{ $notesBody }}</textarea>
                <div class="mw-notes-foot">
                    @if ($role === 'client')
                        <label style="display:inline-flex;align-items:center;gap:7px;cursor:pointer;color:var(--ink-700);">
                            <input type="checkbox" id="mw-notes-share" {{ $notesShared ? 'checked' : '' }}>
                            Share with {{ $otherName }}
                        </label>
                    @endif
                    <span id="mw-notes-state" style="margin-left:auto;">{{ filled($notesBody) ? 'Saved' : '' }}</span>
                </div>
            </div>
        </div>

        <p style="font-size:10.5px;color:var(--ink-300);text-align:center;margin-top:14px;">
            Private between you and {{ $otherName }} · nothing is recorded ·
            @if ($role === 'client')
                you can join from {{ $opensAt->format('g:i A') }}, or as soon as {{ $otherName }} opens the room ·
            @endif
            room open until {{ $closesAt->format('g:i A') }}
        </p>

        {{-- Leave confirmation --}}
        <div class="mw-modal mw-hidden" id="mw-leave-modal" role="dialog" aria-modal="true" aria-labelledby="mw-leave-title">
            <div class="mw-modal-card">
                <div id="mw-leave-title" style="font-size:17px;font-weight:800;color:var(--ink-900);margin-bottom:6px;">Leave the session?</div>
                <p style="font-size:13px;color:var(--ink-500);margin:0 0 18px;">
                    @if ($role === 'client')
                        You can rejoin until {{ $closesAt->format('g:i A') }} if you leave by mistake.
                    @else
                        You'll go to the session report next. You can rejoin until {{ $closesAt->format('g:i A') }}.
                    @endif
                </p>
                <div style="display:flex;gap:10px;justify-content:center;">
                    <button type="button" class="btn ghost" id="mw-leave-cancel" style="border-radius:999px;">Stay</button>
                    <button type="button" class="btn coral" id="mw-leave-confirm" style="border-radius:999px;">Leave</button>
                </div>
            </div>
        </div>

        {{-- Waiting-room copy, per role, chosen by the script below. Kept in the page so
             Blade escapes every name and time; JS reads it as plain text only. --}}
        <template id="mw-copy-client-early">Keep this page open. When {{ $otherName }} opens the room, you'll hear a chime and can join.</template>
        <template id="mw-copy-client-open">Join when you are ready. {{ $otherName }} will see and hear you once you are both in.</template>
        <template id="mw-copy-client-ready">{{ $otherName }} is ready for you. Join now.</template>
        <template id="mw-copy-therapist-early">You can open this room from {{ $opensAt->format('g:i A') }} on {{ $opensAt->format('l, M j') }}.</template>
        <template id="mw-copy-therapist-open">Open the room when you are ready. {{ $otherName }} will be told and can join early.</template>
        <template id="mw-copy-therapist-ready">{{ $otherName }} is already in the room, waiting for you.</template>
        {{-- Short headlines for the waiting hero (2026-11-06). --}}
        <template id="mw-head-client-early">Take a breath. You're early.</template>
        <template id="mw-head-client-open">The room is open</template>
        <template id="mw-head-client-ready">{{ $otherName }} is ready for you</template>
        <template id="mw-head-therapist-early">The room opens at {{ $opensAt->format('g:i A') }}</template>
        <template id="mw-head-therapist-open">Ready when you are</template>
        <template id="mw-head-therapist-ready">{{ $otherName }} is waiting for you</template>

        <script src="https://cdn.jsdelivr.net/npm/livekit-client@2.22.3/dist/livekit-client.umd.js"
                integrity="sha384-G/xxtkVytOx/ia9Q8MXxM+V0ohsaY1fZAgVP3iSGTPz4wJ0s3+ulJNKXh/gnzEDZ"
                crossorigin="anonymous"></script>
        <script>
        (function () {
            var cfg = @json($jsConfig);
            var $ = function (id) { return document.getElementById(id); };

            var state = cfg.state, otherLive = cfg.otherLive;
            var room = null, joined = false, leaving = false, pollTimer = null, beatTimer = null, timerTimer = null;
            var previewVideo = null, previewAudio = null, meterStop = null;
            var baseTitle = document.title;

            function copy(which) { return $('mw-copy-' + cfg.role + '-' + which).content.textContent.trim(); }
            function head(which) { return $('mw-head-' + cfg.role + '-' + which).content.textContent.trim(); }
            function setStatus(text) { $('mw-status').textContent = text; }
            function setCallStatus(text) { $('mw-call-status').textContent = text; }
            function show(el, on) { el.classList.toggle('mw-hidden', !on); }

            // Persistent, human-readable camera/mic problems (these are NOT overwritten by
            // routine status updates — the gap found in the first live test).
            function explainMediaError(kind, e) {
                var name = e && e.name ? e.name : '';
                var what = kind === 'camera' ? 'camera' : 'microphone';
                if (name === 'NotAllowedError' || name === 'PermissionDeniedError') return 'Your ' + what + ' is blocked for this site. Click the camera icon in the address bar, choose "Allow", then try again.';
                if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') return 'Your ' + what + ' is being used by another app or browser (for example Zoom, Teams or another tab). Close it, then try again.';
                if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError') return 'No ' + what + ' was found. Check it is plugged in.';
                return "Your " + what + " couldn't start" + (e && e.message ? ' (' + e.message + ')' : '') + '.';
            }
            var problems = {};
            function setProblem(kind, message) {
                if (message) { problems[kind] = message; } else { delete problems[kind]; }
                var text = Object.keys(problems).map(function (k) { return problems[k]; }).join(' ');
                if (text && (!problems.camera || !problems.microphone)) {
                    text += problems.camera ? ' You can still join with audio only.' : '';
                }
                ['mw-warn-lobby', 'mw-warn-call'].forEach(function (id) { $(id).textContent = text; show($(id), !!text); });
            }

            // ---- Alert: chime + flashing tab title (sound needs one click on the page first)
            var audioCtx = null;
            function ctx() { try { audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {} return audioCtx; }
            document.addEventListener('click', function () { var c = ctx(); if (c && c.state === 'suspended') c.resume(); });
            function chime() {
                var c = ctx(); if (!c) return;
                try {
                    [[660, 0], [880, 0.18]].forEach(function (n) {
                        var o = c.createOscillator(), g = c.createGain(), t = c.currentTime + n[1];
                        o.frequency.value = n[0]; o.connect(g); g.connect(c.destination);
                        g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
                        g.gain.exponentialRampToValueAtTime(0.0001, t + 0.5); o.start(t); o.stop(t + 0.55);
                    });
                } catch (e) {}
            }
            var flashTimer = null;
            function flashTitle(text) { clearInterval(flashTimer); var on = false; flashTimer = setInterval(function () { document.title = (on = !on) ? '● ' + text : baseTitle; }, 1000); }
            function stopFlash() { clearInterval(flashTimer); document.title = baseTitle; }
            window.addEventListener('focus', stopFlash);

            // ---- Waiting room
            function renderWaiting() {
                if (joined) return;
                var ready = otherLive && state === 'open', which = ready ? 'ready' : (state === 'open' ? 'open' : 'early');
                $('mw-lobby').classList.toggle('mw-ready', ready);
                $('mw-lobby').classList.toggle('mw-open', state === 'open' && !ready);
                $('mw-join').disabled = state !== 'open';
                $('mw-join-audio').disabled = state !== 'open';
                $('mw-join').classList.toggle('mwr-glow', ready);
                $('mw-who-text').textContent = copy(which);
                $('mw-who-head').textContent = head(which);
                tickCountdown();
                renderWaitingStatus();
            }
            function renderWaitingStatus() {
                setStatus(state === 'open'
                    ? (previewVideo || previewAudio ? "You're all set." : 'Your browser will ask for camera and microphone access when you join.')
                    : "This page checks every few seconds, so there's no need to refresh.");
            }

            // ---- Countdown to the start time (2026-11-06), from the server's clock
            var cdEl = $('mw-countdown'), startsIn = parseInt(cdEl.dataset.startsIn || '0', 10), cdBase = Date.now();
            function tickCountdown() {
                var left = Math.round(startsIn - (Date.now() - cdBase) / 1000);
                var on = !joined && left > 0 && !(otherLive && state === 'open');
                cdEl.hidden = !on;
                if (!on) return;
                var m = Math.floor(left / 60), sec = left % 60;
                cdEl.textContent = 'Starts in ' + (left >= 3600
                    ? Math.floor(left / 3600) + ' h ' + Math.floor((left % 3600) / 60) + ' min'
                    : m + ':' + (sec < 10 ? '0' : '') + sec);
            }
            setInterval(tickCountdown, 1000);

            // ---- Connection check, screen kept on, get-ready ticks (2026-11-06)
            function setChip(id, kind, text) { var c = $(id); c.className = 'mwr-chip' + (kind ? ' ' + kind : ''); c.textContent = text; }
            MwCalm.keepAwake();
            MwCalm.onNet(function (q) {
                setChip('mw-chk-net', { good: 'ok', weak: 'warn', off: 'bad' }[q] || '',
                    { good: 'Connection good', weak: 'Connection is weak', off: "You're offline" }[q] || 'Checking connection…');
                $('mw-join-audio').hidden = q !== 'weak';
                if (q === 'weak' && !joined) setStatus('Your connection is weak. Move to where your signal is strongest, or join with audio only.');
                else if (!joined) renderWaitingStatus();
            });
            $('mw-ticks').addEventListener('click', function (e) {
                var b = e.target.closest('.mwr-tick'); if (!b) return;
                b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
            });

            async function poll() {
                try {
                    var r = await MwCalm.fetch(cfg.statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!r.ok) return;
                    var s = await r.json();
                    if (s.state === 'ended' || s.state === 'closed') { window.location.reload(); return; }
                    var becameReady = (!otherLive && s.otherLive) || (state !== 'open' && s.state === 'open');
                    state = s.state; otherLive = s.otherLive;
                    renderWaiting();
                    if (becameReady && !joined) {
                        chime();
                        flashTitle(otherLive ? cfg.otherName + (cfg.role === 'client' ? ' is ready' : ' is waiting') : 'Your room is open');
                    }
                } catch (e) { /* offline blip — next tick */ }
            }
            renderWaiting();
            pollTimer = setInterval(poll, 5000);

            // ---- Notes (autosave, works before and during the call)
            var notes = $('mw-notes'), notesState = $('mw-notes-state'), shareBox = $('mw-notes-share');
            var saveTimer = null, dirty = false, notesLocked = false;
            function notesForm() {
                var fd = new FormData();
                fd.append('_token', cfg.csrf);
                fd.append('body', notes.value);
                if (shareBox) fd.append('shared', shareBox.checked ? '1' : '0');
                return fd;
            }
            async function saveNotes() {
                if (notesLocked) return;
                clearTimeout(saveTimer);
                notesState.textContent = 'Saving…';
                try {
                    var r = await fetch(cfg.notesUrl, { method: 'POST', body: notesForm(), credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    if (r.status === 409) {
                        var j = await r.json(); notesLocked = true; notes.readOnly = true; notesState.textContent = j.message || 'Notes are locked.'; return;
                    }
                    if (r.status === 419) { notesState.textContent = 'Please reload the page to keep saving.'; return; }
                    notesState.textContent = r.ok ? 'Saved' : "Not saved. Trying again";
                    dirty = !r.ok;
                    if (!r.ok) saveTimer = setTimeout(saveNotes, 5000);
                } catch (e) { notesState.textContent = "Offline. Trying again"; saveTimer = setTimeout(saveNotes, 5000); }
            }
            notes.addEventListener('input', function () { dirty = true; notesState.textContent = 'Editing…'; clearTimeout(saveTimer); saveTimer = setTimeout(saveNotes, 1200); });
            if (shareBox) shareBox.addEventListener('change', saveNotes);

            // ---- Presence heartbeat while connected
            function beacon(leavingNow) {
                var fd = new FormData(); fd.append('_token', cfg.csrf);
                if (leavingNow) fd.append('leaving', '1');
                if (leavingNow && navigator.sendBeacon) { navigator.sendBeacon(cfg.presenceUrl, fd); return; }
                fetch(cfg.presenceUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
            }

            window.addEventListener('pagehide', function () {
                if (dirty && !notesLocked && navigator.sendBeacon) { navigator.sendBeacon(cfg.notesUrl, notesForm()); }
                if (joined && !leaving) { beacon(true); }
                if (room) { room.disconnect(); }
                [previewVideo, previewAudio].forEach(function (t) { if (t && !joined) t.stop(); });
            });

            if (typeof LivekitClient === 'undefined') {
                $('mw-join').disabled = true; $('mw-join-audio').disabled = true; $('mw-preview-btn').disabled = true;
                clearInterval(pollTimer);
                setStatus("The video component couldn't load. Check your internet connection and reload the page.");
                return;
            }
            var LK = LivekitClient, RoomEvent = LK.RoomEvent, Track = LK.Track;

            // ---- Camera & mic check (only starts when asked — the camera light should
            // never come on just because someone opened the waiting room)
            function startMeter(track) {
                if (meterStop) meterStop();
                var c = ctx(); if (!c || !track) return;
                try {
                    if (c.state === 'suspended') c.resume();
                    var src = c.createMediaStreamSource(new MediaStream([track.mediaStreamTrack]));
                    var an = c.createAnalyser(); an.fftSize = 512; src.connect(an);
                    var buf = new Uint8Array(an.fftSize), raf = null, bar = $('mw-meter');
                    (function tick() {
                        an.getByteTimeDomainData(buf);
                        var peak = 0; for (var i = 0; i < buf.length; i++) { peak = Math.max(peak, Math.abs(buf[i] - 128)); }
                        bar.style.width = Math.min(100, peak * 1.6) + '%';
                        raf = requestAnimationFrame(tick);
                    })();
                    meterStop = function () { cancelAnimationFrame(raf); try { src.disconnect(); } catch (e) {} bar.style.width = '0%'; };
                } catch (e) {}
            }
            async function fillDevices() {
                try {
                    var cams = await LK.Room.getLocalDevices('videoinput', false);
                    var mics = await LK.Room.getLocalDevices('audioinput', false);
                    [[ $('mw-cam-select'), cams, previewVideo ], [ $('mw-mic-select'), mics, previewAudio ]].forEach(function (x) {
                        var sel = x[0]; sel.innerHTML = '';
                        x[1].forEach(function (d, i) {
                            var o = document.createElement('option'); o.value = d.deviceId;
                            o.textContent = d.label || ((sel.id === 'mw-cam-select' ? 'Camera ' : 'Microphone ') + (i + 1));
                            var current = x[2] && x[2].mediaStreamTrack && x[2].mediaStreamTrack.getSettings ? x[2].mediaStreamTrack.getSettings().deviceId : null;
                            if (current && current === d.deviceId) o.selected = true;
                            sel.appendChild(o);
                        });
                    });
                    show($('mw-devices'), cams.length > 1 || mics.length > 1);
                } catch (e) {}
            }
            async function startPreview() {
                $('mw-preview-btn').disabled = true;
                $('mw-preview-hint').textContent = 'Starting…';
                try { previewVideo = previewVideo || await LK.createLocalVideoTrack(); setProblem('camera', null); }
                catch (e) { previewVideo = null; setProblem('camera', explainMediaError('camera', e)); }
                try { previewAudio = previewAudio || await LK.createLocalAudioTrack(); setProblem('microphone', null); }
                catch (e) { previewAudio = null; setProblem('microphone', explainMediaError('microphone', e)); }

                if (previewVideo) {
                    var el = previewVideo.attach(); el.muted = true;
                    $('mw-preview').innerHTML = ''; $('mw-preview').appendChild(el);
                } else {
                    $('mw-preview-hint').textContent = 'Camera unavailable';
                }
                setChip('mw-chk-cam', previewVideo ? 'ok' : 'bad', previewVideo ? 'Camera works' : 'Camera not working');
                setChip('mw-chk-mic', previewAudio ? 'ok' : 'bad', previewAudio ? 'Microphone works' : 'Microphone not working');
                startMeter(previewAudio);
                await fillDevices();
                $('mw-preview-btn').textContent = 'Try again';
                $('mw-preview-btn').disabled = false;
                show($('mw-preview-btn'), !previewVideo || !previewAudio);
                renderWaiting();
            }
            $('mw-preview-btn').addEventListener('click', startPreview);
            $('mw-cam-select').addEventListener('change', function () { if (previewVideo) previewVideo.restartTrack({ deviceId: this.value }).catch(function (e) { setProblem('camera', explainMediaError('camera', e)); }); });
            $('mw-mic-select').addEventListener('change', function () { if (previewAudio) previewAudio.restartTrack({ deviceId: this.value }).then(function () { startMeter(previewAudio); }).catch(function (e) { setProblem('microphone', explainMediaError('microphone', e)); }); });

            // ---- In-call UI
            function refreshInCall() {
                if (!room) return;
                var lp = room.localParticipant;
                var here = room.remoteParticipants.size > 0;
                var hasVideo = !!$('mw-remote').querySelector('video');
                show($('mw-stage-placeholder'), !hasVideo);
                $('mw-stage-text').textContent = here ? cfg.otherName + ' is here, with their camera off.' : 'Waiting for ' + cfg.otherName + ' to join…';
                setCallStatus(here ? cfg.otherName + ' is in the session.' : "You're in. Waiting for " + cfg.otherName + '…');

                var micOn = lp.isMicrophoneEnabled, camOn = lp.isCameraEnabled;
                $('mw-mic').classList.toggle('off', !micOn);
                $('mw-mic').querySelector('.mw-slash').classList.toggle('mw-hidden', micOn);
                $('mw-mic').setAttribute('aria-label', micOn ? 'Mute microphone' : 'Unmute microphone');
                $('mw-mic').title = micOn ? 'Mute microphone' : 'Unmute microphone';
                $('mw-cam').classList.toggle('off', !camOn);
                $('mw-cam').querySelector('.mw-slash').classList.toggle('mw-hidden', camOn);
                $('mw-cam').setAttribute('aria-label', camOn ? 'Turn camera off' : 'Turn camera on');
                $('mw-cam').title = camOn ? 'Turn camera off' : 'Turn camera on';
                show($('mw-chip-mic'), !micOn);
                show($('mw-chip-cam'), !camOn);
                show($('mw-pip-video'), camOn);
            }
            function updateTimer() {
                var left = Math.round((cfg.endsAtMs - Date.now()) / 60000), el = $('mw-timer');
                show(el, true);
                el.classList.toggle('over', left <= 0);
                el.textContent = left > 1 ? left + ' min left' : (left === 1 ? '1 min left' : 'Session time is up');
            }

            // Weak connection: join without the camera (2026-11-06). Video can be turned on later.
            var audioOnly = false;
            $('mw-join-audio').addEventListener('click', function () {
                audioOnly = true;
                if (previewVideo) { previewVideo.stop(); previewVideo = null; }
                $('mw-join').click();
            });

            $('mw-join').addEventListener('click', async function () {
                var btn = this;
                btn.disabled = true; $('mw-join-audio').disabled = true; stopFlash(); setStatus('Connecting…');

                var creds;
                try {
                    var fd = new FormData(); fd.append('_token', cfg.csrf);
                    var r = await fetch(cfg.tokenUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    if (r.status === 419) { setStatus('This page has been open for a long time. Please reload it, then join.'); return; }
                    creds = await r.json();
                    if (!r.ok) {
                        if (creds.state === 'ended' || creds.state === 'closed') { window.location.reload(); return; }
                        if (creds.state) { state = creds.state; }
                        renderWaiting(); setStatus(creds.message || "Couldn't join right now.");
                        return;
                    }
                } catch (e) { setStatus("Couldn't reach the server. Check your connection and try again."); btn.disabled = false; $('mw-join-audio').disabled = false; audioOnly = false; return; }

                room = new LK.Room({ adaptiveStream: true, dynacast: true });
                room.on(RoomEvent.TrackSubscribed, function (track) {
                    var el = track.attach();
                    if (track.kind === Track.Kind.Video) { $('mw-remote').innerHTML = ''; $('mw-remote').appendChild(el); }
                    else { el.style.display = 'none'; document.body.appendChild(el); }
                    refreshInCall();
                });
                room.on(RoomEvent.TrackUnsubscribed, function (track) { track.detach().forEach(function (el) { el.remove(); }); refreshInCall(); });
                room.on(RoomEvent.LocalTrackPublished, function (pub) {
                    if (pub.track && pub.track.kind === Track.Kind.Video) {
                        var el = pub.track.attach(); el.muted = true;
                        $('mw-pip-video').innerHTML = ''; $('mw-pip-video').appendChild(el);
                    }
                    refreshInCall();
                });
                room.on(RoomEvent.ParticipantConnected, function () { chime(); refreshInCall(); });
                room.on(RoomEvent.ParticipantDisconnected, refreshInCall);
                room.on(RoomEvent.Disconnected, function () {
                    clearInterval(beatTimer);
                    if (leaving) return;
                    setCallStatus('You were disconnected. Reload the page to rejoin.');
                    show(document.querySelector('.mw-bar'), false);
                });

                try { await room.connect(creds.url, creds.token); }
                catch (e) { setStatus("Couldn't connect to the video service: " + (e && e.message ? e.message : e)); btn.disabled = false; $('mw-join-audio').disabled = false; audioOnly = false; room = null; return; }

                joined = true;
                clearInterval(pollTimer);
                if (meterStop) meterStop();
                beacon(false);
                beatTimer = setInterval(function () { beacon(false); }, 15000);
                updateTimer(); timerTimer = setInterval(updateTimer, 30000);
                show($('mw-lobby'), false); show($('mw-call'), true);
                refreshInCall();

                var lp = room.localParticipant;
                // Camera and mic are started separately so one failing doesn't take the
                // other down; any failure stays visible in the warning box.
                if (audioOnly) { /* camera stays off; the camera button turns it on */ }
                else if (previewVideo) { try { await lp.publishTrack(previewVideo); } catch (e) { setProblem('camera', explainMediaError('camera', e)); } }
                else { try { await lp.setCameraEnabled(true); setProblem('camera', null); } catch (e) { setProblem('camera', explainMediaError('camera', e)); } }
                if (previewAudio) { try { await lp.publishTrack(previewAudio); } catch (e) { setProblem('microphone', explainMediaError('microphone', e)); } }
                else { try { await lp.setMicrophoneEnabled(true); setProblem('microphone', null); } catch (e) { setProblem('microphone', explainMediaError('microphone', e)); } }
                refreshInCall();
            });

            $('mw-mic').addEventListener('click', async function () {
                if (!room) return;
                try { await room.localParticipant.setMicrophoneEnabled(!room.localParticipant.isMicrophoneEnabled); setProblem('microphone', null); }
                catch (e) { setProblem('microphone', explainMediaError('microphone', e)); }
                refreshInCall();
            });
            $('mw-cam').addEventListener('click', async function () {
                if (!room) return;
                try { await room.localParticipant.setCameraEnabled(!room.localParticipant.isCameraEnabled); setProblem('camera', null); }
                catch (e) { setProblem('camera', explainMediaError('camera', e)); }
                refreshInCall();
            });

            // ---- Session feedback on the thank-you screen (2026-10-04)
            var rateForm = $('mw-rate');
            async function sendRating(payload) {
                var err = $('mw-rate-error');
                show(err, false);
                try {
                    var res = await fetch(cfg.rateUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                        body: JSON.stringify(payload),
                    });
                    if (!res.ok) { throw new Error(res.status); }
                    show(rateForm, false);
                    if (!payload.skip) { show($('mw-rate-done'), true); }
                } catch (e) {
                    err.textContent = "That did not send. Please try again.";
                    show(err, true);
                }
            }
            if (rateForm && cfg.rateUrl) {
                rateForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var picked = rateForm.querySelector('input[name=rating]:checked');
                    if (!picked) {
                        $('mw-rate-error').textContent = 'Tap a star to rate, or press Skip.';
                        show($('mw-rate-error'), true);
                        return;
                    }
                    sendRating({ rating: parseInt(picked.value, 10), comment: rateForm.comment.value });
                });
                $('mw-rate-skip').addEventListener('click', function () { sendRating({ skip: true }); });
                rateForm.querySelectorAll('input[name=rating]').forEach(function (r) {
                    r.addEventListener('change', function () { show($('mw-rate-error'), false); });
                });
            }

            // ---- Leaving (with confirmation)
            $('mw-leave').addEventListener('click', function () { show($('mw-leave-modal'), true); $('mw-leave-cancel').focus(); });
            $('mw-leave-cancel').addEventListener('click', function () { show($('mw-leave-modal'), false); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') show($('mw-leave-modal'), false); });
            $('mw-leave-confirm').addEventListener('click', async function () {
                leaving = true;
                show($('mw-leave-modal'), false);
                clearInterval(beatTimer); clearInterval(timerTimer);
                beacon(true);
                if (dirty) { await saveNotes(); }
                if (room) { await room.disconnect(); }
                MwCalm.letSleep();
                if (cfg.role === 'therapist') { window.location.href = cfg.leaveUrl; return; }
                show($('mw-call'), false); show($('mw-timer'), false); show($('mw-thanks'), true);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        })();
        </script>
    @endif
</div>
@endsection
