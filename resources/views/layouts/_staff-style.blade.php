{{-- Staff look (2026-11-03): admin and therapist pages in the same logo colours, font and
     status chips as the client refresh, but calmer and denser (no plants, no big hero).
     Collins approved the proposal PDF with one addition: the sidebar can be navy (default)
     or the logo's deep green, chosen once for the whole clinic in Admin → Setup →
     Appearance (AppSetting "staff.sidebar"). Only the sidebar changes colour.
     Old teal/coral variables are re-pointed here, so every existing staff page follows
     without editing it. --}}
<style>
    @font-face {
        font-family: 'Plus Jakarta Sans';
        font-style: normal;
        font-weight: 200 800;
        font-display: swap;
        src: url('{{ asset('fonts/plus-jakarta-sans-latin.woff2') }}') format('woff2');
    }

    body.mw-staff {
        --mw-navy: #034C8F;
        --mw-navy-900: #031F3F;
        --mw-navy-tint: #E3EDF8;
        --mw-green: #00A87C;
        --mw-green-tint: #DDF4EC;
        --mw-amber: #B7791F;
        --mw-amber-tint: #FBF0D9;
        --mw-red: #D64545;
        --mw-red-tint: #FBE3E3;
        --mw-purple: #7B5CC4;
        --mw-purple-tint: #EEE8FA;
        --mw-line: #E3E9F2;

        /* Existing variables, re-pointed to the logo colours */
        --teal-900: #022F5C;
        --teal-700: #034C8F;
        --teal-600: #0B5FB0;
        --teal-500: #2A7BC8;
        --teal-100: #E3EDF8;
        --teal-50: #F1F6FC;
        --coral-600: #D96A00;
        --coral-500: #EE7800;
        --coral-100: #FFEEDB;
        --mw-teal-tint: #E3EDF8;
        --mw-coral-tint: #FFEBD6;
        --mw-teal-dot: #00A87C;
        --mw-orange: #EE7800;
        --mw-orange-soft: #FFB766;
        --mw-note-info: #034C8F;
        --mw-canvas: #F4F7FB;
        --ink-900: #14213A;
        --ink-700: #3A4A63;
        --ink-500: #66758C;
        --ink-300: #9AA6B8;
        --ink-100: #E3E9F2;
        --success: #00795A;
        --success-bg: #DDF4EC;

        /* Sidebar: navy (default) */
        --mw-nav-bg: linear-gradient(180deg, #031F3F 0%, #04305E 55%, #053A70 100%);
        --mw-nav-fg: rgba(255,255,255,.80);
        --mw-nav-strong: #FFFFFF;
        --mw-nav-muted: rgba(255,255,255,.48);
        --mw-nav-hover: rgba(255,255,255,.08);
        --mw-nav-line: rgba(255,255,255,.09);
        --mw-nav-active: rgba(255,255,255,.11);

        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: var(--mw-canvas);
        color: var(--ink-900);
    }
    /* Sidebar: the logo's deep green (Admin → Setup → Appearance). */
    body.mw-staff[data-nav="green"] {
        --mw-nav-bg: linear-gradient(180deg, #013A32 0%, #02594B 55%, #047058 100%);
    }
    body.mw-staff button, body.mw-staff input, body.mw-staff select, body.mw-staff textarea { font-family: inherit; }

    /* Shell details */
    body.mw-staff .mw-nav-tag { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); }
    body.mw-staff .mw-nav-item.is-active .mw-nav-ic { color: #fff; }
    body.mw-staff .mw-nav-item.is-active::before { width: 3px; }
    body.mw-staff .mw-nav-avatar { background: var(--mw-orange); }
    body.mw-staff .mw-appbar { border-bottom-color: var(--mw-line); }
    body.mw-staff .mw-content { background-color: var(--mw-canvas); }

    /* Cards, KPIs, lists */
    :where(body.mw-staff) .card, :where(body.mw-staff) .mw-kpi {
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(3,31,63,.04), 0 6px 18px -12px rgba(3,31,63,.14), 0 0 0 1px var(--mw-line);
    }
    body.mw-staff .mw-kpi { position: relative; overflow: hidden; }
    body.mw-staff .mw-kpi .k { display: flex; align-items: center; gap: 8px; }
    body.mw-staff .mw-kpi .k .ic { width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 28px; }
    body.mw-staff .mw-kpi .k .ic svg { width: 15px; height: 15px; }
    body.mw-staff .mw-kpi .spark { position: absolute; right: 14px; top: 16px; }
    body.mw-staff .mw-kpi .v small { font-size: 13px; color: var(--ink-500); font-weight: 700; margin-right: 3px; }
    body.mw-staff .mw-queue .ic { background: var(--mw-navy-tint); color: var(--mw-navy); }
    body.mw-staff .mw-queue .ic.warm { background: var(--mw-coral-tint); color: #C46200; }
    body.mw-staff .mw-queue .ic.urgent { background: var(--mw-red-tint); color: var(--mw-red); }
    body.mw-staff .mw-queue .ic.purple { background: var(--mw-purple-tint); color: var(--mw-purple); }
    body.mw-staff .mw-queue li:hover { background: #F6F9FD; }
    body.mw-staff .mw-tabs a.on .n { background: var(--mw-coral-tint); color: #C46200; }
    body.mw-staff .mw-table tbody tr:hover td { background: #F6F9FD; }
    body.mw-staff .mw-search input:focus, body.mw-staff .mw-appsearch input:focus { border-color: var(--mw-navy); box-shadow: 0 0 0 3px rgba(3,76,143,.12); }
    body.mw-staff .mw-code { background: #FFF4E5; border-color: #F0B060; }

    /* Avatars in the logo family */
    body.mw-staff .mw-av.c0 { background: #034C8F; } body.mw-staff .mw-av.c1 { background: #EE7800; }
    body.mw-staff .mw-av.c2 { background: #00A87C; } body.mw-staff .mw-av.c3 { background: #7B5CC4; }
    body.mw-staff .mw-av.c4 { background: #1C64A8; }

    /* Buttons */
    body.mw-staff .btn.primary { background: var(--mw-navy); }
    body.mw-staff .btn.primary:hover { background: #022F5C; }
    body.mw-staff .btn.ghost { border-color: var(--mw-navy); color: var(--mw-navy); }
    body.mw-staff .btn.ghost:hover { background: #F1F6FC; }
    body.mw-staff .btn.muted { background: #fff; color: var(--ink-500); border: 1.5px solid var(--mw-line); }

    /* Inputs with a fixed height (filters, toolbars) clipped their text with the
       default 11px padding ("All tvpes", 2026-11-03). */
    body.mw-staff .mw-field[style*="height"] { padding-top: 0; padding-bottom: 0; }
    body.mw-staff .mw-field:focus { border-color: var(--mw-navy); box-shadow: 0 0 0 3px rgba(3,76,143,.12); }

    /* Status chips, shared with the client side's colours */
    .mw-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; white-space: nowrap; line-height: 1.4; }
    .mw-chip .dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; display: inline-block; }
    .mw-chip.navy { background: var(--mw-navy-tint, #E3EDF8); color: #034C8F; }
    .mw-chip.green { background: #DDF4EC; color: #00795A; }
    .mw-chip.amber { background: #FBF0D9; color: #8A5A10; }
    .mw-chip.red { background: #FBE3E3; color: #B03232; }
    .mw-chip.grey { background: #EEF1F5; color: #66758C; }
    .mw-chip.purple { background: #EEE8FA; color: #5B3FA6; }
    .mw-chip.test { background: #fff; color: #8A5300; border: 1px dashed #E0B060; }
    .mw-chip.live { background: #DDF4EC; color: #00795A; }
    .mw-chip.live .dot { box-shadow: 0 0 0 3px rgba(0,168,124,.2); animation: mw-pulse 1.8s ease-in-out infinite; }
    @keyframes mw-pulse { 50% { box-shadow: 0 0 0 6px rgba(0,168,124,0); } }

    /* Date block (same as the client side) */
    .mw-dateblock { width: 50px; height: 54px; border-radius: 12px; background: #034C8F; color: #fff; display: inline-flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1; flex: 0 0 50px; }
    .mw-dateblock .m { font-size: 10px; font-weight: 800; letter-spacing: .08em; opacity: .8; }
    .mw-dateblock .d { font-size: 19px; font-weight: 800; margin: 3px 0 2px; }
    .mw-dateblock .w { font-size: 10px; opacity: .85; }
    .mw-dateblock.soft { background: var(--mw-navy-tint, #E3EDF8); color: #034C8F; }
    .mw-dateblock.glass { background: rgba(255,255,255,.14); }

    /* Test-site ribbon (staging and local only, never live) */
    .mw-ribbon { position: sticky; top: 0; z-index: 60; height: 26px; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0 12px;
        background: repeating-linear-gradient(135deg, #FFF4E0 0 14px, #FDEBD0 14px 28px); color: #8A5300; font-size: 11.5px; font-weight: 700;
        border-bottom: 1px solid #F3D7A8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mw-ribbon b { background: #8A5300; color: #fff; border-radius: 4px; padding: 1px 6px; font-size: 10px; letter-spacing: .06em; }
    body.has-ribbon .mw-nav { top: 26px; height: calc(100vh - 26px); }
    body.has-ribbon .mw-appbar { top: 26px; }

    /* Phones: agenda rows put their action (chip + button) on its own line. */
    @media (max-width: 720px) {
        body.mw-staff .mw-agenda li { grid-template-columns: 58px auto minmax(0,1fr); }
        body.mw-staff .mw-agenda li > div:last-child { grid-column: 2 / -1; justify-content: flex-start; }
        .mw-ribbon .host { display: none; }
        .mw-ribbon { justify-content: flex-start; }
    }

    /* Therapist bottom tabs on phones */
    .mw-tabbar { display: none; }
    @media (max-width: 960px) {
        body.mw-staff .mw-tabbar { display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 45; height: 62px; background: #fff; border-top: 1px solid var(--mw-line); padding-bottom: env(safe-area-inset-bottom); }
        body.mw-staff .mw-tabbar a { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; text-decoration: none; color: var(--ink-300); font-size: 10.5px; font-weight: 700; position: relative; }
        body.mw-staff .mw-tabbar a svg { width: 21px; height: 21px; }
        body.mw-staff .mw-tabbar a.on { color: var(--mw-navy); }
        body.mw-staff .mw-tabbar .cnt { position: absolute; top: 6px; left: calc(50% + 6px); min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px; background: var(--mw-orange); color: #fff; font-size: 9.5px; line-height: 16px; text-align: center; }
        body.mw-staff.has-tabbar .mw-content { padding-bottom: 92px; }
        body.has-ribbon .mw-nav { top: 0; height: 100vh; }
    }
</style>
