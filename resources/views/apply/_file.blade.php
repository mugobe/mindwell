{{-- One upload slot on an application step. Needs $app and $kind (config onboarding.documents). --}}
@php
    $mwDoc = $app->document($kind);
    $mwCfg = config('onboarding.documents.'.$kind);
@endphp
<div class="ap-file {{ $mwDoc ? 'has' : '' }}">
    <span class="ic">
        @if ($mwDoc)
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
        @else
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 20h14"/></svg>
        @endif
    </span>
    <div style="flex:1;min-width:0;">
        <div class="t">{{ $mwCfg['label'] }} @unless ($mwCfg['required'])<span class="ap-opt">(optional)</span>@endunless</div>
        <div class="s">
            @if ($mwDoc)
                {{ \Illuminate\Support\Str::limit($mwDoc->original_name, 42) }} · {{ $mwDoc->sizeLabel() }} · uploaded {{ $mwDoc->created_at->format('j M') }}
            @else
                PDF, JPG or PNG, up to {{ (int) (config('onboarding.upload.max_kb') / 1024) }} MB. Only the Mindwell team can see it.
            @endif
        </div>
        @error('doc_'.$kind)<div class="ap-err">{{ $message }}</div>@enderror
    </div>
    <label class="btn ghost sm" style="cursor:pointer;white-space:nowrap;">
        {{ $mwDoc ? 'Replace' : 'Choose file' }}
        <input type="file" name="doc_{{ $kind }}" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="this.closest('.ap-file').querySelector('.s').textContent = this.files[0] ? this.files[0].name + ' (saves when you continue)' : ''">
    </label>
    @if ($mwDoc)
        <button type="submit" form="rm-{{ $kind }}" class="btn muted sm" onclick="return confirm('Remove this file?')">Remove</button>
    @endif
</div>
