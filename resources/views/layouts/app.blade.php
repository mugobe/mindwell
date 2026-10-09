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
            /* Brand teal, deep at the top to lighter at the bottom (Collins picked option C, 2026-10-08). */
            --mw-nav-bg: linear-gradient(180deg, #2F6F66 0%, #317A73 50%, #3A8C88 100%);
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

        /* ---------------------------------------------------------------------------
           Page kit (2026-10-08, Doctolib-style middle area). Reusable on any page:
           mw-page-head, mw-tabs, mw-kpis, mw-queue, mw-menu, mw-av, mw-empty.
           ------------------------------------------------------------------------- */
        .mw-page-head { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
        .mw-page-head h1 { font-size:24px; line-height:1.2; font-weight:800; letter-spacing:-.015em; color:var(--ink-900, #1d2b29); margin:0; }
        .mw-page-head .sub { font-size:13.5px; color:var(--ink-500, #5f6f6b); margin-top:4px; }
        .mw-page-head .actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .mw-tabs { display:flex; gap:4px; flex-wrap:wrap; border-bottom:1px solid rgba(16,48,43,.1); margin-bottom:16px; }
        .mw-tabs a { display:inline-flex; align-items:center; gap:7px; padding:9px 12px; margin-bottom:-1px; font-size:13px; font-weight:600; color:var(--ink-500, #5f6f6b); text-decoration:none; border-bottom:2px solid transparent; }
        .mw-tabs a:hover { color:var(--ink-900, #1d2b29); }
        .mw-tabs a.on { color:var(--ink-900, #1d2b29); border-bottom-color:var(--mw-orange); }
        .mw-tabs a .n { font-size:11px; font-weight:700; padding:1px 7px; border-radius:999px; background:rgba(16,48,43,.07); color:var(--ink-500, #5f6f6b); }
        .mw-tabs a.on .n { background:rgba(232,130,94,.15); color:#B4532F; }
        .mw-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:14px; }
        .mw-search { position:relative; flex:1; min-width:220px; max-width:380px; }
        .mw-search svg { position:absolute; left:11px; top:50%; width:16px; height:16px; transform:translateY(-50%); color:var(--ink-300, #98a5a1); pointer-events:none; }
        .mw-search input { width:100%; box-sizing:border-box; height:38px; padding:0 12px 0 34px; border:1px solid rgba(16,48,43,.14); border-radius:10px; background:#fff; font:inherit; font-size:13.5px; color:var(--ink-900, #1d2b29); }
        .mw-search input:focus { outline:none; border-color:var(--teal-600, #2f6f66); box-shadow:0 0 0 3px rgba(47,111,102,.12); }
        .mw-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:14px; margin-bottom:18px; }
        .mw-kpi { background:#fff; border-radius:14px; padding:16px 18px; box-shadow:0 1px 2px rgba(16,48,43,.05), 0 0 0 1px rgba(16,48,43,.05); text-decoration:none; display:block; }
        .mw-kpi .k { font-size:12px; font-weight:600; color:var(--ink-500, #5f6f6b); }
        .mw-kpi .v { font-size:26px; font-weight:800; letter-spacing:-.02em; color:var(--ink-900, #1d2b29); margin-top:6px; font-variant-numeric:tabular-nums; }
        .mw-kpi .d { font-size:12px; color:var(--ink-500, #5f6f6b); margin-top:2px; }
        .mw-kpi .d .up { color:#1e7a4f; font-weight:700; } .mw-kpi .d .down { color:#b4532f; font-weight:700; }
        .mw-card-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:16px 18px 10px; }
        .mw-card-head h2 { font-size:15px; font-weight:800; color:var(--ink-900, #1d2b29); margin:0; }
        .mw-card-head a { font-size:12.5px; font-weight:700; color:var(--teal-600, #2f6f66); text-decoration:none; }
        .mw-queue { list-style:none; margin:0; padding:0 8px 8px; }
        .mw-queue li { display:flex; align-items:center; gap:12px; padding:11px 10px; border-radius:10px; }
        .mw-queue li + li { border-top:1px solid rgba(16,48,43,.06); }
        .mw-queue li:hover { background:rgba(47,111,102,.035); }
        .mw-queue .ic { width:36px; height:36px; flex:0 0 36px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:rgba(47,111,102,.1); color:var(--teal-600, #2f6f66); }
        .mw-queue .ic svg { width:18px; height:18px; }
        .mw-queue .ic.urgent { background:rgba(214,69,69,.1); color:#C0392B; }
        .mw-queue .ic.warm { background:rgba(232,130,94,.14); color:#B4532F; }
        .mw-queue .t { font-size:13.5px; font-weight:700; color:var(--ink-900, #1d2b29); }
        .mw-queue .s { font-size:12px; color:var(--ink-500, #5f6f6b); margin-top:1px; }
        .mw-queue .num { margin-left:auto; font-size:20px; font-weight:800; color:var(--ink-900, #1d2b29); font-variant-numeric:tabular-nums; min-width:28px; text-align:right; }
        .mw-queue .num.urgent { color:#C0392B; }
        .mw-agenda { list-style:none; margin:0; padding:0 18px 12px; }
        .mw-agenda li { display:grid; grid-template-columns:70px auto minmax(0,1fr) auto; gap:10px; align-items:center; padding:10px 0; }
        .mw-agenda li + li { border-top:1px solid rgba(16,48,43,.06); }
        .mw-agenda .time { font-size:13px; font-weight:800; color:var(--ink-900, #1d2b29); font-variant-numeric:tabular-nums; }
        .mw-agenda .time.past { color:var(--ink-300, #98a5a1); }
        .mw-agenda .who { font-size:13px; font-weight:700; color:var(--ink-900, #1d2b29); }
        .mw-agenda .meta { font-size:11.5px; color:var(--ink-500, #5f6f6b); }
        .mw-av { width:32px; height:32px; flex:0 0 32px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:11.5px; font-weight:800; color:#fff; vertical-align:middle; letter-spacing:.02em; user-select:none; }
        .mw-av.c0 { background:#2F6F66; } .mw-av.c1 { background:#E8825E; } .mw-av.c2 { background:#6B8E4E; } .mw-av.c3 { background:#4A7FA8; } .mw-av.c4 { background:#8A6BA8; }
        .mw-meter { display:inline-block; width:46px; height:5px; border-radius:3px; background:rgba(16,48,43,.08); overflow:hidden; vertical-align:middle; } .mw-meter > span { display:block; height:100%; border-radius:3px; }
        summary.mw-card-head { list-style:none; } summary.mw-card-head::-webkit-details-marker { display:none; } summary.mw-card-head h2::before { content:'+'; display:inline-block; width:18px; color:var(--mw-orange); } details[open] > summary.mw-card-head h2::before { content:'–'; }
        .mw-code { display:inline-block; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12.5px; font-weight:800; letter-spacing:.06em; color:var(--ink-900, #1d2b29); background:#FDF1EC; border:1px dashed #E8825E; border-radius:7px; padding:3px 8px; }
        .mw-person { display:flex; align-items:center; gap:10px; min-width:0; }
        .mw-person-t { min-width:0; }
        .mw-person .nm { font-weight:700; color:var(--ink-900); text-decoration:none; line-height:1.3; }
        a.nm:hover { color:var(--teal-600, #2f6f66); text-decoration:underline; }
        .mw-person .sb { font-size:11.5px; color:var(--ink-500); line-height:1.35; overflow:hidden; text-overflow:ellipsis; }
        .mw-avstack { display:inline-flex; } .mw-avstack .mw-av { box-shadow:0 0 0 2px #fff; } .mw-avstack .mw-av + .mw-av { margin-left:-8px; }
        .mw-dot { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-700, #3b4a47); }
        .mw-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:#98a5a1; }
        .mw-dot.ok::before { background:#2f9e6a; } .mw-dot.bad::before { background:#D64545; } .mw-dot.wait::before { background:#E0A33B; }
        .mw-menu { position:relative; display:inline-block; }
        .mw-menu > summary { list-style:none; cursor:pointer; width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; color:var(--ink-500, #5f6f6b); }
        .mw-menu > summary::-webkit-details-marker { display:none; }
        .mw-menu > summary:hover, .mw-menu[open] > summary { background:rgba(16,48,43,.07); color:var(--ink-900, #1d2b29); }
        .mw-menu .pop { position:absolute; right:0; top:calc(100% + 4px); z-index:25; min-width:210px; background:#fff; border-radius:12px; padding:6px; box-shadow:0 12px 32px rgba(16,48,43,.16), 0 0 0 1px rgba(16,48,43,.06); text-align:left; }
        .mw-menu .pop form { margin:0; }
        .mw-menu .pop a:not(.btn), .mw-menu .pop button:not(.btn) { display:block; width:100%; text-align:left; padding:8px 10px; border-radius:8px; border:0; background:none; font:inherit; font-size:13px; color:var(--ink-900, #1d2b29); cursor:pointer; text-decoration:none; }
        .mw-menu .pop a:not(.btn):hover, .mw-menu .pop button:not(.btn):hover { background:rgba(16,48,43,.05); }
        .mw-menu .pop .danger { color:#C0392B; }
        .mw-menu .pop hr { border:0; border-top:1px solid rgba(16,48,43,.08); margin:4px 2px; }
        .mw-empty { text-align:center; padding:34px 20px; color:var(--ink-500, #5f6f6b); font-size:13px; }
        .mw-empty .big { font-size:15px; font-weight:800; color:var(--ink-900, #1d2b29); margin:10px 0 2px; }
        .mw-empty svg { width:40px; height:40px; color:var(--teal-600, #2f6f66); opacity:.7; }
        /* Gentle polish for every existing table. */
        .mw-table tbody tr:hover td { background:rgba(47,111,102,.03); }
        .mw-table th { font-size:11px; letter-spacing:.05em; }
        .mw-appsearch { position:relative; width:min(360px, 40vw); margin-left:18px; }
        .mw-appsearch svg { position:absolute; left:11px; top:50%; width:16px; height:16px; transform:translateY(-50%); color:var(--ink-300, #98a5a1); pointer-events:none; }
        .mw-appsearch input { width:100%; box-sizing:border-box; height:36px; padding:0 54px 0 34px; border:1px solid rgba(16,48,43,.12); border-radius:10px; background:rgba(16,48,43,.035); font:inherit; font-size:13px; color:var(--ink-900, #1d2b29); }
        .mw-appsearch input:focus { outline:none; background:#fff; border-color:var(--teal-600, #2f6f66); box-shadow:0 0 0 3px rgba(47,111,102,.12); }
        .mw-appsearch kbd { position:absolute; right:9px; top:50%; transform:translateY(-50%); font:inherit; font-size:10.5px; font-weight:700; color:var(--ink-300, #98a5a1); border:1px solid rgba(16,48,43,.14); border-radius:6px; padding:1px 6px; background:#fff; }
        @media (max-width: 960px) {
            .mw-nav { position:fixed; left:0; top:0; bottom:0; transform:translateX(-100%); transition:transform .2s ease; box-shadow:0 0 40px rgba(0,0,0,.25); }
            body.mw-nav-open .mw-nav { transform:none; }
            body.mw-nav-open .mw-nav-scrim { display:block; position:fixed; inset:0; background:rgba(16,48,43,.35); z-index:35; }
            .mw-appbar { padding:0 16px; }
            .mw-appbar .menu-btn { display:inline-flex; }
            .mw-appbar .crumb .ctx { display:none; }
            .mw-appsearch { display:none; }
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
                    @if (auth()->user()->role === 'admin')
                        {{-- Global search (2026-10-08): clients, therapists, receipts — from any page. --}}
                        <form method="GET" action="{{ route('admin.search') }}" class="mw-appsearch" role="search">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                            <input id="mw-appsearch" type="search" name="q" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" placeholder="Search clients, therapists, receipts…" autocomplete="off" aria-label="Search">
                            <kbd>Ctrl K</kbd>
                        </form>
                        <script>
                            document.addEventListener('keydown', function (e) {
                                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); var i = document.getElementById('mw-appsearch'); if (i) { i.focus(); i.select(); } }
                            });
                        </script>
                    @endif
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
