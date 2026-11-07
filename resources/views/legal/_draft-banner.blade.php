@if (config('mindwell.legal.is_draft'))
    <div class="mw-note warn top">
        <div class="mw-note-body">
            <div class="mw-note-title">Draft under review</div>
            <div class="mw-note-text">
                Dr. Ntabade and our legal advisers are still reviewing this text.
                The final version will replace it before Mindwell opens to the public.
                Version: {{ config('mindwell.legal.version') }}.
            </div>
        </div>
    </div>
@endif
