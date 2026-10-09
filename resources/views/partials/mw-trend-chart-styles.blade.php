{{-- Styles for partials.mw-trend-chart — include ONCE per page (added 2026-10-03). --}}
<style>
    .mw-tc { position:relative; }
    .mw-tc-plot { position:relative; height:var(--mw-tc-h, 150px); margin-right:84px; }
    .mw-tc-plot svg { position:absolute; inset:0; width:100%; height:100%; overflow:visible; }
    .mw-tc-grid { position:absolute; left:0; right:0; border-top:1px dashed rgba(0,0,0,.09); }
    .mw-tc-grid.base { border-top:1px solid rgba(0,0,0,.16); }
    .mw-tc-grid span { position:absolute; left:calc(100% + 8px); top:-8px; font-size:10.5px; color:var(--ink-500); white-space:nowrap; }
    .mw-tc-dot { position:absolute; width:10px; height:10px; border-radius:50%; transform:translate(-50%,-50%); box-shadow:0 0 0 2px #fff; cursor:default; outline:none; z-index:2; }
    .mw-tc-dot::before { content:''; position:absolute; inset:-8px; border-radius:50%; }
    .mw-tc-dot.flag { box-shadow:0 0 0 2px #fff, 0 0 0 4px #c0392b; }
    .mw-tc-tip { display:none; position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%); background:#1d2b29; color:#fff; font-size:11.5px; line-height:1.45; padding:6px 9px; border-radius:8px; white-space:nowrap; pointer-events:none; z-index:5; }
    .mw-tc-dot.right .mw-tc-tip { left:auto; right:-6px; transform:none; }
    .mw-tc-dot.left .mw-tc-tip { left:-6px; transform:none; }
    .mw-tc-dot:hover .mw-tc-tip, .mw-tc-dot:focus .mw-tc-tip { display:block; }
    .mw-tc-dot:hover, .mw-tc-dot:focus { width:12px; height:12px; }
    .mw-tc-x { display:flex; justify-content:space-between; margin:6px 84px 0 0; font-size:11px; color:var(--ink-500); }
    .mw-tc-empty { font-size:12.5px; color:var(--ink-500); padding:18px 0; }
</style>
