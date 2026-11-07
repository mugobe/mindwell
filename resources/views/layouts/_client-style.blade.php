{{-- Client look (2026-11-01 refresh, Collins: the client side felt "plain and boring").
     Loaded only for signed-in clients, so admin and therapist pages are unchanged.
     - Logo colours: the old teal/coral variables are re-pointed to the logo's navy,
       green and orange, so every existing page and inline style follows without edits.
     - Plus Jakarta Sans, served from public/fonts (no Google request, works offline).
     - Shell: white sidebar with icons on computers, bottom tab bar on phones (the
       pattern Gmail, Google Meet and most therapy apps use).
     - Motion: page content rises in, cards lift on hover, buttons press, pages cross-fade.
       All of it switches off for people who ask their device for reduced motion. --}}
<style>
    @font-face {
        font-family: 'Plus Jakarta Sans';
        font-style: normal;
        font-weight: 200 800;
        font-display: swap;
        src: url('{{ asset('fonts/plus-jakarta-sans-latin.woff2') }}') format('woff2');
    }

    body.mw-client {
        /* Logo colours */
        --mw-navy: #034C8F;
        --mw-navy-900: #022F5C;
        --mw-navy-tint: #E6EFF9;
        --mw-green: #00A87C;
        --mw-green-500: #00B888;
        --mw-green-tint: #DDF5EC;
        --mw-brand-orange: #F88000;

        /* Existing variables, re-pointed */
        --teal-900: #022F5C;
        --teal-700: #034C8F;
        --teal-600: #0B5FB0;
        --teal-500: #2A7BC8;
        --teal-100: #E6EFF9;
        --teal-50: #F3F7FC;
        --coral-600: #D96A00;
        --coral-500: #EE7800;
        --coral-100: #FFEEDB;
        --mw-teal-tint: #E6EFF9;
        --mw-coral-tint: #FFEBD6;
        --mw-sage: #DDF5EC;
        --mw-gold: #FDEBC8;
        --mw-blush: #F7E1EA;
        --mw-teal-dot: #00B888;
        --mw-orange: #EE7800;
        --mw-orange-soft: #FFB766;
        --mw-note-info: #034C8F;
        --mw-canvas: #F4F7FB;
        --success: #0E8F68;
        --success-bg: #DFF5EC;

        --mwc-line: rgba(3, 40, 80, .08);
        --mwc-shadow: 0 1px 2px rgba(3, 40, 80, .05), 0 0 0 1px rgba(3, 40, 80, .05);
        --mwc-shadow-hover: 0 10px 28px rgba(3, 40, 80, .10), 0 0 0 1px rgba(3, 40, 80, .06);
        --mwc-ease: cubic-bezier(.2, .8, .2, 1);

        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: var(--mw-canvas);
        color: var(--ink-900);
        margin: 0;
    }
    body.mw-client button, body.mw-client input, body.mw-client select, body.mw-client textarea { font-family: inherit; }

    /* ---------- Shell ---------- */
    .mwc { min-height: 100vh; }
    .mwc-side {
        position: fixed; top: 0; left: 0; bottom: 0; width: 256px; z-index: 40;
        background: #fff; border-right: 1px solid var(--mwc-line);
        display: flex; flex-direction: column; padding: 18px 14px 14px;
        view-transition-name: mwc-side;
    }
    .mwc-logo { display: block; padding: 4px 10px 18px; }
    .mwc-logo img { height: 40px; width: auto; display: block; }
    .mwc-navlist { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 2px; scrollbar-width: thin; }
    .mwc-label { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-300); padding: 16px 14px 6px; }
    .mwc-item {
        position: relative; display: flex; align-items: center; gap: 14px; height: 44px; padding: 0 14px;
        border-radius: 999px; color: var(--ink-700); font-size: 14px; font-weight: 600; text-decoration: none;
        transition: background .18s var(--mwc-ease), color .18s var(--mwc-ease);
    }
    .mwc-item svg { width: 21px; height: 21px; flex: 0 0 21px; transition: transform .25s var(--mwc-ease); }
    .mwc-item:hover { background: rgba(3, 76, 143, .06); color: var(--mw-navy); }
    .mwc-item:hover svg { transform: scale(1.08); }
    .mwc-item.is-on { background: var(--mw-navy-tint); color: var(--mw-navy); font-weight: 800; }
    .mwc-item:focus-visible, .mwc-tab:focus-visible, .mwc-help:focus-visible { outline: 2px solid var(--mw-navy); outline-offset: 2px; }
    .mwc-count {
        margin-left: auto; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px;
        background: var(--mw-brand-orange); color: #fff; font-size: 11px; font-weight: 800; line-height: 20px; text-align: center;
    }
    .mwc-help {
        display: flex; align-items: center; gap: 12px; margin: 12px 4px 10px; padding: 12px 14px; border-radius: 16px;
        background: #FDECEC; color: #A61B1B; text-decoration: none; font-size: 13px; line-height: 1.35;
        transition: transform .2s var(--mwc-ease), box-shadow .2s var(--mwc-ease);
    }
    .mwc-help:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(166, 27, 27, .12); }
    .mwc-help b { display: block; font-size: 13.5px; }
    .mwc-help svg { width: 22px; height: 22px; flex: 0 0 22px; }
    .mwc-me { display: flex; align-items: center; gap: 10px; padding: 12px 8px 4px; border-top: 1px solid var(--mwc-line); }
    .mwc-avatar {
        width: 36px; height: 36px; flex: 0 0 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, var(--mw-navy), var(--mw-green-500)); color: #fff; font-weight: 800; font-size: 14px;
    }
    .mwc-me .who { flex: 1; min-width: 0; }
    .mwc-me .nm { font-size: 13.5px; font-weight: 700; color: var(--ink-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mwc-me .sb { font-size: 11.5px; color: var(--ink-500); }
    .mwc-me form { margin: 0; }
    .mwc-iconbtn {
        width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%;
        border: 0; background: transparent; color: var(--ink-500); cursor: pointer; transition: background .15s, color .15s;
    }
    .mwc-iconbtn:hover { background: rgba(3, 76, 143, .07); color: var(--mw-navy); }
    .mwc-iconbtn svg { width: 20px; height: 20px; }

    .mwc-main { margin-left: 256px; min-height: 100vh; }
    .mwc-wrap { max-width: 1120px; margin: 0 auto; padding: 34px 40px 64px; }

    /* Phone top bar + bottom tabs (hidden on computers) */
    .mwc-top, .mwc-tabs { display: none; }

    @media (max-width: 960px) {
        .mwc-side { display: none; }
        .mwc-main { margin-left: 0; }
        .mwc-wrap { padding: 18px 16px 104px; }
        .mwc-top {
            display: flex; align-items: center; gap: 8px; position: sticky; top: 0; z-index: 40; height: 60px; padding: 0 10px 0 16px;
            background: rgba(255, 255, 255, .94); backdrop-filter: saturate(1.2) blur(10px); border-bottom: 1px solid var(--mwc-line);
            view-transition-name: mwc-top;
        }
        .mwc-top .mwc-logo { padding: 0; margin-right: auto; }
        .mwc-top .mwc-logo img { height: 32px; }
        .mwc-top-help { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border-radius: 999px; background: #FDECEC; color: #A61B1B; font-size: 12.5px; font-weight: 800; text-decoration: none; }
        .mwc-top-help svg { width: 16px; height: 16px; }
        .mwc-tabs {
            display: grid; grid-template-columns: repeat(5, 1fr); position: fixed; left: 0; right: 0; bottom: 0; z-index: 40;
            height: 68px; padding-bottom: env(safe-area-inset-bottom); background: #fff; border-top: 1px solid var(--mwc-line);
            view-transition-name: mwc-tabs;
        }
        .mwc-tab { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--ink-500); font-size: 11.5px; font-weight: 700; text-decoration: none; }
        .mwc-tab .pillbg { width: 56px; height: 30px; border-radius: 999px; display: flex; align-items: center; justify-content: center; transition: background .2s var(--mwc-ease); }
        .mwc-tab svg { width: 22px; height: 22px; }
        .mwc-tab.is-on { color: var(--mw-navy); }
        .mwc-tab.is-on .pillbg { background: var(--mw-navy-tint); }
        .mwc-tab .mwc-count { position: absolute; top: 5px; left: 50%; margin-left: 6px; }
    }

    /* Account menu (phone top bar) */
    .mwc-menu { position: relative; }
    .mwc-menu > summary { list-style: none; cursor: pointer; }
    .mwc-menu > summary::-webkit-details-marker { display: none; }
    .mwc-menu .pop {
        position: absolute; right: 0; top: calc(100% + 8px); z-index: 50; width: 240px; background: #fff; border-radius: 16px; padding: 8px;
        box-shadow: 0 16px 40px rgba(3, 40, 80, .18), 0 0 0 1px rgba(3, 40, 80, .06); animation: mwcPop .18s var(--mwc-ease) both;
    }
    .mwc-menu .pop a, .mwc-menu .pop button { display: flex; align-items: center; gap: 12px; width: 100%; padding: 10px 12px; border: 0; border-radius: 10px; background: none; color: var(--ink-900); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; text-align: left; }
    .mwc-menu .pop a:hover, .mwc-menu .pop button:hover { background: var(--mw-navy-tint); }
    .mwc-menu .pop svg { width: 19px; height: 19px; color: var(--ink-500); }
    .mwc-menu .pop hr { border: 0; border-top: 1px solid var(--mwc-line); margin: 6px 4px; }
    .mwc-menu .pop form { margin: 0; }
    @keyframes mwcPop { from { opacity: 0; transform: translateY(-6px) scale(.98); } to { opacity: 1; transform: none; } }

    /* ---------- Page pieces ----------
       :where() keeps these at plain-class strength, so a page's own styles still win. */
    :where(body.mw-client) .dash-head { margin-bottom: 24px; }
    :where(body.mw-client) .dash-head h1 { font-size: 26px; font-weight: 800; letter-spacing: -.02em; color: var(--ink-900); margin-right: auto; }
    :where(body.mw-client) .card { border: 0; border-radius: 20px; box-shadow: var(--mwc-shadow); padding: 20px; transition: box-shadow .25s var(--mwc-ease), transform .25s var(--mwc-ease); }
    :where(body.mw-client) a.card:hover, :where(body.mw-client) .mwc-lift:hover { transform: translateY(-2px); box-shadow: var(--mwc-shadow-hover); }
    :where(body.mw-client) .btn { transition: background .18s var(--mwc-ease), box-shadow .18s var(--mwc-ease), transform .12s var(--mwc-ease); }
    :where(body.mw-client) .btn:hover { box-shadow: 0 4px 14px rgba(3, 40, 80, .12); }
    :where(body.mw-client) .btn:active, :where(body.mw-client) .mw-pill-btn:active { transform: scale(.97); }
    :where(body.mw-client) .btn.primary, :where(body.mw-client) .btn.coral { border-radius: 999px; }
    :where(body.mw-client) .btn.ghost { border-color: rgba(3, 76, 143, .35); }
    :where(body.mw-client) .mw-pill-btn { transition: background .18s, transform .12s; }
    :where(body.mw-client) .mw-field { border-radius: 12px; transition: border-color .15s, box-shadow .15s; }
    :where(body.mw-client) .pill.teal { background: var(--mw-navy-tint); color: var(--mw-navy); }
    :where(body.mw-client) .mw-note { border-radius: 18px; }
    :where(body.mw-client) .mw-table tbody tr:hover td { background: rgba(3, 76, 143, .03); }

    /* Section heading inside pages */
    .mwc-h2 { font-size: 16px; font-weight: 800; color: var(--ink-900); margin: 0 0 12px; letter-spacing: -.01em; }
    .mwc-sub { font-size: 13.5px; color: var(--ink-500); margin-top: 4px; }

    /* ---------- Motion ---------- */
    @keyframes mwcRise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    .mwc-wrap > *, .mwc-stagger > * { animation: mwcRise .5s var(--mwc-ease) both; }
    .mwc-wrap > *:nth-child(2), .mwc-stagger > *:nth-child(2) { animation-delay: .05s; }
    .mwc-wrap > *:nth-child(3), .mwc-stagger > *:nth-child(3) { animation-delay: .10s; }
    .mwc-wrap > *:nth-child(4), .mwc-stagger > *:nth-child(4) { animation-delay: .15s; }
    .mwc-wrap > *:nth-child(5), .mwc-stagger > *:nth-child(5) { animation-delay: .20s; }
    .mwc-wrap > *:nth-child(n+6), .mwc-stagger > *:nth-child(n+6) { animation-delay: .25s; }
    .mwc-wrap > style, .mwc-wrap > script, .mwc.is-focus .mwc-wrap > * { animation: none; }
    @media (max-width: 960px) { .mwc.is-focus .mwc-tabs { display: none; } .mwc.is-focus .mwc-wrap { padding-bottom: 32px; } }

    /* Smooth cross-fade between pages (Chrome and Edge; other browsers just load normally). */
    @view-transition { navigation: auto; }
    ::view-transition-old(root), ::view-transition-new(root) { animation-duration: .22s; }

    @media (prefers-reduced-motion: reduce) {
        .mwc-wrap > *, .mwc-stagger > *, .mwc-menu .pop { animation: none !important; }
        body.mw-client *, body.mw-client *::before, body.mw-client *::after { transition: none !important; }
        ::view-transition-old(root), ::view-transition-new(root) { animation: none !important; }
    }
</style>
