{{-- Therapist onboarding look (2026-11-03). Shared by the application steps and the status page. --}}
<style>
    .ap-wrap { max-width: 860px; margin: 0 auto; }
    .ap-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin: 4px 0 18px; flex-wrap: wrap; }
    .ap-head h1 { font-size: 24px; font-weight: 800; letter-spacing: -.02em; color: var(--ink-900); margin: 0; }
    .ap-head .sub { color: var(--ink-500); font-size: 13.5px; margin-top: 4px; }
    .ap-steps { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-bottom: 18px; counter-reset: s; }
    .ap-steps a { display: flex; flex-direction: column; gap: 6px; text-decoration: none; color: var(--ink-500); font-size: 11.5px; font-weight: 700; }
    .ap-steps a .bar { height: 5px; border-radius: 99px; background: var(--mw-line, #E3E9F2); }
    .ap-steps a.done .bar { background: #00A87C; }
    .ap-steps a.on .bar { background: #034C8F; }
    .ap-steps a.on { color: #034C8F; }
    .ap-steps a.todo-flag .lbl::after { content: ' •'; color: #EE7800; }
    .ap-card { background: #fff; border-radius: 16px; box-shadow: 0 1px 2px rgba(3,31,63,.04), 0 6px 18px -12px rgba(3,31,63,.14), 0 0 0 1px var(--mw-line, #E3E9F2); padding: 22px 24px; margin-bottom: 14px; }
    .ap-card h2 { font-size: 16px; font-weight: 800; margin: 0 0 4px; color: var(--ink-900); }
    .ap-card .hint { font-size: 12.5px; color: var(--ink-500); margin: 0 0 16px; line-height: 1.5; }
    .ap-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
    .ap-grid .full { grid-column: 1 / -1; }
    .ap-opt { font-weight: 400; color: var(--ink-300); }
    .ap-help { font-size: 11.5px; color: var(--ink-500); margin-top: 5px; line-height: 1.45; }
    .ap-err { font-size: 12px; color: #B03232; font-weight: 600; margin-top: 5px; }
    .ap-checks { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 8px; }
    .ap-check { display: flex; align-items: flex-start; gap: 9px; padding: 9px 11px; border-radius: 10px; box-shadow: 0 0 0 1px var(--mw-line, #E3E9F2); font-size: 13px; color: var(--ink-700); cursor: pointer; background: #fff; }
    .ap-check:has(input:checked) { box-shadow: 0 0 0 1.5px #034C8F; background: #F1F6FC; color: var(--ink-900); }
    .ap-check input { margin-top: 2px; accent-color: #034C8F; }
    .ap-yn { display: inline-flex; border-radius: 10px; overflow: hidden; box-shadow: 0 0 0 1px var(--mw-line, #E3E9F2); }
    .ap-yn label { padding: 7px 16px; font-size: 13px; font-weight: 700; color: var(--ink-500); cursor: pointer; background: #fff; }
    .ap-yn input { position: absolute; opacity: 0; pointer-events: none; }
    .ap-yn label:has(input:checked) { background: #034C8F; color: #fff; }
    .ap-yn label.yes:has(input:checked) { background: #B7791F; }
    .ap-decl { padding: 14px 0; border-top: 1px solid var(--mw-line, #E3E9F2); }
    .ap-decl:first-of-type { border-top: 0; padding-top: 0; }
    .ap-decl .q { display: flex; gap: 16px; align-items: flex-start; justify-content: space-between; font-size: 13.5px; color: var(--ink-900); line-height: 1.5; }
    .ap-file { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; border: 1.5px dashed #C7D3E3; background: #FAFCFE; }
    .ap-file.has { border-style: solid; border-color: #BFE7D8; background: #F3FBF8; }
    .ap-file .ic { width: 36px; height: 36px; border-radius: 9px; background: #E3EDF8; color: #034C8F; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 36px; }
    .ap-file.has .ic { background: #DDF4EC; color: #00795A; }
    .ap-file .ic svg { width: 18px; height: 18px; }
    .ap-file .t { font-weight: 700; font-size: 13.5px; color: var(--ink-900); }
    .ap-file .s { font-size: 12px; color: var(--ink-500); }
    .ap-file input[type=file] { font-size: 12px; max-width: 230px; }
    .ap-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
    .ap-actions .r { display: flex; gap: 10px; }
    .ap-missing { background: #FFF8EC; border: 1px solid #F3D7A8; color: #6B4A10; border-radius: 12px; padding: 12px 14px; font-size: 13px; margin-bottom: 14px; }
    .ap-missing ul { margin: 6px 0 0; padding-left: 18px; }
    .ap-consent .doc { max-height: 210px; overflow: auto; border-radius: 12px; background: #F6F9FD; padding: 14px 16px; font-size: 13px; color: var(--ink-700); line-height: 1.6; }
    .ap-consent .doc li { margin-bottom: 6px; }
    .ap-consent .agree { display: flex; gap: 10px; align-items: flex-start; margin-top: 12px; font-size: 13.5px; font-weight: 600; color: var(--ink-900); }
    .ap-consent .agree input { margin-top: 3px; width: 17px; height: 17px; accent-color: #034C8F; }
    .ap-sum dl { display: grid; grid-template-columns: 210px 1fr; gap: 8px 16px; margin: 0; font-size: 13.5px; }
    .ap-sum dt { color: var(--ink-500); }
    .ap-sum dd { margin: 0; color: var(--ink-900); font-weight: 600; }
    .ap-sum .edit { font-size: 12.5px; font-weight: 700; color: #034C8F; text-decoration: none; }
    .ap-track { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; margin: 6px 0 4px; }
    .ap-track .st { position: relative; text-align: center; font-size: 12.5px; font-weight: 700; color: var(--ink-300); padding-top: 34px; }
    .ap-track .st::before { content: ''; position: absolute; top: 11px; left: -50%; right: 50%; height: 3px; background: var(--mw-line, #E3E9F2); }
    .ap-track .st:first-child::before { display: none; }
    .ap-track .st .dot { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 25px; height: 25px; border-radius: 50%; background: #fff; box-shadow: 0 0 0 2px var(--mw-line, #E3E9F2); display: flex; align-items: center; justify-content: center; z-index: 1; }
    .ap-track .st.done { color: #00795A; } .ap-track .st.done::before { background: #00A87C; }
    .ap-track .st.done .dot { background: #00A87C; box-shadow: none; }
    .ap-track .st.done .dot::after { content: ''; width: 9px; height: 5px; border: 2px solid #fff; border-top: 0; border-right: 0; transform: rotate(-45deg) translate(1px, -1px); }
    .ap-track .st.now { color: #034C8F; } .ap-track .st.now::before { background: #00A87C; }
    .ap-track .st.now .dot { box-shadow: 0 0 0 2px #034C8F; } .ap-track .st.now .dot::after { content: ''; width: 9px; height: 9px; border-radius: 50%; background: #034C8F; }
    .ap-track .st.warn { color: #8A5A10; } .ap-track .st.warn .dot { box-shadow: 0 0 0 2px #B7791F; } .ap-track .st.warn .dot::after { content: '!'; font-size: 13px; color: #B7791F; font-weight: 800; }
    .ap-track .st.bad { color: #B03232; } .ap-track .st.bad .dot { box-shadow: 0 0 0 2px #D64545; } .ap-track .st.bad .dot::after { content: '×'; font-size: 15px; color: #D64545; font-weight: 800; }
    .ap-track .when { display: block; font-weight: 500; color: var(--ink-500); font-size: 11.5px; margin-top: 2px; }
    @media (max-width: 720px) {
        .ap-grid { grid-template-columns: 1fr; }
        .ap-steps { grid-template-columns: repeat(3, 1fr); row-gap: 10px; }
        .ap-sum dl { grid-template-columns: 1fr; gap: 2px 0; } .ap-sum dd { margin-bottom: 8px; }
        .ap-decl .q { flex-direction: column; gap: 8px; }
        .ap-card { padding: 18px; }
        .ap-file { flex-wrap: wrap; }
    }
</style>
