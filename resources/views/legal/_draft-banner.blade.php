@if (config('mindwell.legal.is_draft'))
    <div style="background:var(--mw-gold, #F6E1A8);color:#6b4a12;border-radius:12px;padding:12px 14px;font-size:12.5px;line-height:1.55;margin-bottom:18px;">
        <strong>Draft — under review.</strong> This text is a working draft prepared for Mindwell and is being reviewed
        by Dr. Ntabade and legal counsel. It will be replaced with the final version before Mindwell opens to the public.
        Version: {{ config('mindwell.legal.version') }}.
    </div>
@endif
