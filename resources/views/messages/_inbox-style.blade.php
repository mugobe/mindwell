{{-- Two-pane messages layout (2026-11-01 refresh): conversation list on the left, chat on
     the right, like WhatsApp Web and Google Chat. On phones only one pane shows. --}}
<style>
    .mw-panes { display:grid; grid-template-columns: 340px minmax(0, 1fr); gap:18px; height: calc(100vh - 140px); min-height: 480px; }
    .mw-pane { background:#fff; border-radius:20px; box-shadow: 0 1px 2px rgba(3,40,80,.05), 0 0 0 1px rgba(3,40,80,.05); overflow:hidden; display:flex; flex-direction:column; min-height:0; }
    .mw-pane-head { padding:18px 20px 12px; font-size:20px; font-weight:800; color:var(--ink-900); letter-spacing:-.01em; }
    .mw-pane-list { flex:1; overflow-y:auto; padding:0 8px 8px; }
    .mw-pane-foot { padding:12px 18px; font-size:11.5px; color:var(--ink-300); border-top:1px solid rgba(3,40,80,.06); line-height:1.5; }
    .mw-thread { display:flex; align-items:center; gap:12px; padding:12px; border-radius:14px; text-decoration:none; color:inherit; transition:background .15s; }
    .mw-thread:hover { background:rgba(3,76,143,.05); }
    .mw-thread.is-on { background:var(--mw-navy-tint, #E6EFF9); }
    .mw-thread-avatar { flex-shrink:0; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:17px; color:#fff; background:#034C8F; }
    .mw-thread-avatar.c1 { background:#00A87C; } .mw-thread-avatar.c2 { background:#EE7800; } .mw-thread-avatar.c3 { background:#7A4CC2; }
    .mw-thread-main { flex:1; min-width:0; }
    .mw-thread-name { font-size:14px; font-weight:700; color:var(--ink-900); }
    .mw-thread-preview { font-size:12.5px; color:var(--ink-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
    .mw-thread.unread .mw-thread-name, .mw-thread.unread .mw-thread-preview { color:var(--ink-900); font-weight:800; }
    .mw-thread-meta { flex-shrink:0; text-align:right; font-size:11.5px; color:var(--ink-300); display:flex; flex-direction:column; align-items:flex-end; gap:5px; }
    .mw-count { min-width:20px; height:20px; padding:0 6px; border-radius:10px; background:var(--mw-brand-orange, #e8825e); color:#fff; font-size:11px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
    .mw-pane-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:30px; color:var(--ink-500); font-size:13.5px; }
    @media (max-width: 960px) {
        .mw-panes { grid-template-columns: 1fr; height: auto; min-height: 0; }
        .mw-panes.is-chat .mw-pane.list { display:none; }
        .mw-panes.is-inbox .mw-pane.chat { display:none; }
    }
</style>
