<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Mindwell</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Warm canvas background (2026-09-26 feedback: flat white pages read as too
           plain next to BetterHelp). Kept self-contained here rather than in app.css,
           since this sandbox has never actually had that file's real contents to edit
           safely. A soft cream base + a very faint botanical line-doodle pattern,
           mostly visible in the margins around cards/tables rather than under them. */
        /* 2026-10-08: cooler "mist" canvas (Collins: the cream was too warm). A near-white
           with a faint teal tint — calm, clinical-clean, and white cards still lift off it. */
        body {
            background-color: var(--mw-canvas, #F3F6F5);
        }

        /* Client-facing pastel palette (2026-09-27 design pass) — a small family of soft,
           warm tints all derived from the existing teal/coral/cream identity, used for
           illustrations, chips and banners on client-facing pages only. Deliberately kept
           separate from the core --teal-*/--coral-*/--ink-* variables (defined in the real
           app.css this sandbox has never had visible contents for) so nothing here can
           collide with or override those. Admin/therapist pages don't reference these and
           are unaffected. */
        :root {
            --mw-teal-tint: #DCEDE8;
            --mw-coral-tint: #FCE1D3;
            --mw-sage: #DEE8CE;
            --mw-blush: #F5DCE2;
            --mw-gold: #F6E1A8;
            --mw-teal-dot: #6FA99B;
        }

        /* App shell for admin + therapist (2026-10-08 redesign): full-height grouped
           sidebar (layouts._nav) + slim app bar. Deep brand teal with the Mindwell
           orange as the single accent — current page, counts, focus. */
        :root {
            --mw-orange: #E8825E;
            --mw-orange-soft: #F3B08F;
            /* Sidebar theme — change these to re-colour the whole menu. */
            --mw-canvas: #F3F6F5;
            /* Brand teal, light to deep, top to bottom (Collins picked option A, 2026-10-08). */
            --mw-nav-bg: linear-gradient(180deg, #3B8579 0%, #2F6F66 55%, #2A6259 100%);
            --mw-nav-fg: rgba(255,255,255,.82);
            --mw-nav-strong: #FFFFFF;
            --mw-nav-muted: rgba(255,255,255,.55);
            --mw-nav-hover: rgba(255,255,255,.09);
            --mw-nav-line: rgba(255,255,255,.12);
            --mw-nav-active: rgba(255,255,255,.14);
            --mw-nav-count-neutral: rgba(255,255,255,.18);
        }
        /* On the cooler canvas, cards get a hairline edge so they read crisply. */
        .card { box-shadow:0 1px 2px rgba(16,48,43,.05), 0 0 0 1px rgba(16,48,43,.05); }
        .mw-shell { display:flex; min-height:100vh; }
        .mw-nav { width:252px; flex:0 0 252px; background:var(--mw-nav-bg); color:var(--mw-nav-fg); position:sticky; top:0; height:100vh; display:flex; flex-direction:column; z-index:40; }
        .mw-nav-brand { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:18px 18px 16px; border-bottom:1px solid var(--mw-nav-line); }
        .mw-nav-logo { display:flex; align-items:center; gap:10px; color:var(--mw-nav-strong); font-weight:800; font-size:17px; letter-spacing:-.01em; text-decoration:none; }
        .mw-nav-logo .mark { width:12px; height:12px; border-radius:50%; background:var(--mw-orange); box-shadow:0 0 0 5px rgba(232,130,94,.28); margin:0 3px; }
        .mw-nav-tag { font-size:10px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#fff; background:var(--mw-orange); padding:4px 8px; border-radius:999px; }
        .mw-nav-scroll { flex:1; overflow-y:auto; padding:12px 12px 18px; scrollbar-width:thin; scrollbar-color:var(--mw-nav-line) transparent; }
        .mw-nav-group + .mw-nav-group { margin-top:14px; }
        .mw-nav-label { font-size:10.5px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:var(--mw-nav-muted); padding:4px 10px 6px; }
        .mw-nav-item { position:relative; display:flex; align-items:center; gap:11px; height:34px; padding:0 10px; border-radius:8px; color:var(--mw-nav-fg); font-size:13.5px; font-weight:500; text-decoration:none; transition:background .12s, color .12s; }
        .mw-nav-item:hover { background:var(--mw-nav-hover); color:var(--mw-nav-strong); }
        .mw-nav-item:focus-visible, .mw-nav-gear:focus-visible, .mw-appbar a:focus-visible, .mw-appbar button:focus-visible { outline:2px solid var(--mw-orange); outline-offset:2px; }
        .mw-nav-item.is-active { background:var(--mw-nav-active); color:var(--mw-nav-strong); font-weight:700; }
        .mw-nav-item.is-active::before { content:''; position:absolute; left:-12px; top:7px; bottom:7px; width:4px; border-radius:0 4px 4px 0; background:var(--mw-orange); }
        .mw-nav-ic { width:18px; height:18px; flex:0 0 18px; opacity:.85; }
        .mw-nav-item.is-active .mw-nav-ic { color:var(--mw-orange-soft); opacity:1; }
        .mw-nav-text { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mw-nav-count { min-width:20px; height:20px; padding:0 6px; border-radius:999px; background:var(--mw-orange); color:#fff; font-size:11px; font-weight:800; line-height:20px; text-align:center; font-variant-numeric:tabular-nums; }
        .mw-nav-count.is-urgent { background:#D64545; }
        .mw-nav-foot { display:flex; align-items:center; gap:10px; padding:12px 14px; border-top:1px solid var(--mw-nav-line); }
        .mw-nav-avatar { width:32px; height:32px; flex:0 0 32px; border-radius:50%; background:var(--mw-orange); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; }
        .mw-nav-me { flex:1; min-width:0; }
        .mw-nav-me .name { color:var(--mw-nav-strong); font-size:13px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mw-nav-me .role { color:var(--mw-nav-muted); font-size:11px; }
        .mw-nav-gear { width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:8px; color:var(--mw-nav-muted); }
        .mw-nav-gear:hover { background:var(--mw-nav-hover); color:var(--mw-nav-strong); }
        .mw-nav-gear svg { width:17px; height:17px; }
        .mw-nav-scrim { display:none; }
        .mw-main { flex:1; min-width:0; display:flex; flex-direction:column; }
        .mw-appbar { position:sticky; top:0; z-index:30; height:58px; display:flex; align-items:center; gap:12px; padding:0 28px; background:rgba(255,255,255,.92); backdrop-filter:saturate(1.2) blur(8px); border-bottom:1px solid rgba(16,48,43,.08); }
        .mw-appbar .crumb { font-size:12.5px; color:var(--ink-300, #98a5a1); font-weight:600; }
        .mw-appbar .crumb strong { color:var(--ink-900, #1d2b29); font-weight:800; }
        .mw-appbar .right { margin-left:auto; display:flex; align-items:center; gap:10px; }
        .mw-appbar .icon-btn { width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:9px; color:var(--ink-500, #5f6f6b); background:transparent; border:0; cursor:pointer; }
        .mw-appbar .icon-btn:hover { background:rgba(16,48,43,.06); color:var(--ink-900, #1d2b29); }
        .mw-appbar .icon-btn svg { width:19px; height:19px; }
        .mw-appbar .menu-btn { display:none; }
        .mw-content { flex:1; padding:28px 32px 56px; }
        @media (max-width: 960px) {
            .mw-nav { position:fixed; left:0; top:0; bottom:0; transform:translateX(-100%); transition:transform .2s ease; box-shadow:0 0 40px rgba(0,0,0,.25); }
            body.mw-nav-open .mw-nav { transform:none; }
            body.mw-nav-open .mw-nav-scrim { display:block; position:fixed; inset:0; background:rgba(16,48,43,.35); z-index:35; }
            .mw-appbar { padding:0 16px; }
            .mw-appbar .menu-btn { display:inline-flex; }
            .mw-appbar .crumb .ctx { display:none; }
            .mw-appbar .btn { white-space:nowrap; }
            .mw-content { padding:20px 16px 48px; }
        }
    </style>
</head>
<body>

@php
    $verifiedTherapist = auth()->check()
        && auth()->user()->role === 'therapist'
        && auth()->user()->therapistProfile
        && auth()->user()->therapistProfile->credentials_verified;

    // Unread messages for the "Messages" nav badge (added 2026-09-29). Never throws —
    // returns 0 if the chat tables don't exist yet, so this can't break every page.
    $mwUnread = \App\Models\ChatConversation::unreadTotalFor(auth()->user());

    // Same warm-cream + faint botanical-line-doodle background used across every
    // content canvas (admin, therapist, client) -- see the note in <head> above.
    $mwCanvasBg = "background-color:var(--mw-canvas, #F3F6F5); background-image:url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27240%27 height=%27240%27 viewBox=%270 0 240 240%27%3E%3Cg fill=%27none%27 stroke=%27%232f6f66%27 stroke-width=%271.5%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 opacity=%270.045%27%3E%3Cg transform=%27translate(40,50) rotate(-12)%27%3E%3Cellipse cx=%270%27 cy=%27-8%27 rx=%274%27 ry=%277%27/%3E%3Cellipse cx=%270%27 cy=%278%27 rx=%274%27 ry=%277%27/%3E%3Cellipse cx=%27-8%27 cy=%270%27 rx=%277%27 ry=%274%27/%3E%3Cellipse cx=%278%27 cy=%270%27 rx=%277%27 ry=%274%27/%3E%3Ccircle cx=%270%27 cy=%270%27 r=%273%27/%3E%3C/g%3E%3Cg transform=%27translate(175,120) rotate(18)%27%3E%3Cpath d=%27M0 46 C 3 26 3 12 0 0%27/%3E%3Cpath d=%27M0 30 C 12 24 17 12 9 2%27/%3E%3Cpath d=%27M0 18 C -12 12 -16 1 -8 -6%27/%3E%3C/g%3E%3Cg transform=%27translate(55,190) rotate(8)%27%3E%3Cellipse cx=%270%27 cy=%27-7%27 rx=%273.5%27 ry=%276%27/%3E%3Cellipse cx=%270%27 cy=%277%27 rx=%273.5%27 ry=%276%27/%3E%3Cellipse cx=%27-7%27 cy=%270%27 rx=%276%27 ry=%273.5%27/%3E%3Cellipse cx=%277%27 cy=%270%27 rx=%276%27 ry=%273.5%27/%3E%3Ccircle cx=%270%27 cy=%270%27 r=%272.6%27/%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); background-repeat:repeat; background-size:240px 240px;";
@endphp

@php $mwShell = auth()->check() && (auth()->user()->role === 'admin' || $verifiedTherapist); @endphp
@unless ($mwShell)
<div class="mw-topbar">
    <a href="{{ route('dashboard') }}" class="mw-brand">
        <span class="dot"></span> Mindwell
    </a>

    @auth
        @if (auth()->user()->role === 'admin')
            <div style="font-size:12px;font-weight:700;color:var(--ink-300);">Admin console</div>
        @elseif ($verifiedTherapist)
            <div style="font-size:12px;font-weight:700;color:var(--ink-300);">Therapist portal</div>
        @endif
        <div style="display:flex;align-items:center;gap:14px;">
            @if (auth()->user()->role === 'client')
                <a href="{{ route('sessions.history') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">My sessions</a>
                <a href="{{ route('messages.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);display:inline-flex;align-items:center;gap:5px;">Messages<span class="mw-unread-badge" style="{{ $mwUnread ? '' : 'display:none;' }}min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:var(--coral-500,#e8825e);color:#fff;font-size:10.5px;font-weight:800;line-height:18px;text-align:center;">{{ $mwUnread }}</span></a>
                <a href="{{ route('journal.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">My journal</a>
                <a href="{{ route('progress.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">My progress</a>
                <a href="{{ route('subscription.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">My plan</a>
                <a href="{{ route('payments.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">Payments</a>
                <a href="{{ route('employer-seat.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">Employer benefit</a>
                <a href="{{ route('settings.details') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">My details</a>
                {{-- Phase 0 (2026-10-05): always one tap from crisis contacts. --}}
                <a href="{{ route('urgent-help') }}" style="font-size:13px;font-weight:800;color:var(--danger, #c0392b);">Get help now</a>
            @endif
            <a href="{{ route('settings.notifications') }}" title="Notification settings" style="font-size:13px;color:var(--ink-500);display:inline-flex;align-items:center;gap:5px;">
                {{ auth()->user()->name }}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;" aria-hidden="true"><path d="M6 9a6 6 0 0112 0c0 5 2 6.5 2 6.5H4S6 14 6 9z"/><path d="M10 19a2 2 0 004 0"/></svg>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn ghost sm">Log out</button>
            </form>
        </div>
    @endauth
</div>
@endunless

@auth
    @if ($mwShell)
        {{-- Admin + therapist shell (2026-10-08 redesign): grouped sidebar + app bar. --}}
        <div class="mw-shell">
            @include('layouts._nav', ['mwNavRole' => auth()->user()->role === 'admin' ? 'admin' : 'therapist', 'mwUnread' => $mwUnread])
            <div class="mw-main">
                <header class="mw-appbar">
                    <button type="button" class="icon-btn menu-btn" aria-label="Open menu" onclick="document.body.classList.add('mw-nav-open')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    <div class="crumb"><span class="ctx">{{ auth()->user()->role === 'admin' ? 'Admin console' : 'Therapist portal' }} &nbsp;/&nbsp; </span><strong>@yield('title', 'Dashboard')</strong></div>
                    <div class="right">
                        <a href="{{ route('settings.notifications') }}" class="icon-btn" title="Notification settings" aria-label="Notification settings">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9a6 6 0 0112 0c0 5 2 6.5 2 6.5H4S6 14 6 9z"/><path d="M10 19a2 2 0 004 0"/></svg>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn ghost sm">Log out</button>
                        </form>
                    </div>
                </header>
                <main class="mw-content" style="{{ $mwCanvasBg }}">
                    @if (session('status'))
                        <div class="mw-alert success">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="mw-alert danger">{{ session('error') }}</div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        {{-- Client / unverified therapist: plain content area, no sidebar. --}}
        <div style="max-width:960px;margin:0 auto;padding:28px;min-height:100vh;{{ $mwCanvasBg }}">
            @if (session('status'))
                <div class="mw-alert success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mw-alert danger">{{ session('error') }}</div>
            @endif
            @yield('content')
        </div>
    @endif
@else
    <div style="max-width:960px;margin:0 auto;padding:28px;min-height:100vh;{{ $mwCanvasBg }}">
        @yield('content')
    </div>
@endauth

@auth
    @if (in_array(auth()->user()->role, ['client', 'therapist'], true))
        {{-- Keeps the "Messages" unread badge current (added 2026-09-29). Polls every
             30s, or every 2 minutes while the tab is in the background. --}}
        <script>
        (function () {
            var url = @json(route('messages.unread'));
            function refresh() {
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) {
                        if (!d) return;
                        document.querySelectorAll('.mw-unread-badge').forEach(function (b) {
                            b.textContent = d.count; b.style.display = d.count > 0 ? '' : 'none';
                        });
                    })
                    .catch(function () {})
                    .finally(function () { setTimeout(refresh, document.hidden ? 120000 : 30000); });
            }
            setTimeout(refresh, 30000);
        })();
        </script>
    @endif
@endauth
</body>
</html>
