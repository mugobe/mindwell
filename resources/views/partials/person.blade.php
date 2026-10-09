{{-- Avatar + name (+ optional second line / link) for list rows (2026-10-08).
     @include('partials.person', ['name' => $u?->name, 'sub' => $u?->email, 'href' => route(...), 'size' => 32, 'fallback' => 'Client']) --}}
@php
    $mwPName = trim((string) ($name ?? '')) ?: ($fallback ?? '—');
    $mwPSize = (int) ($size ?? 32);
@endphp
<div class="mw-person">
    @include('partials.avatar', ['name' => $mwPName, 'size' => $mwPSize])
    <div class="mw-person-t">
        @if (! empty($href))
            <a href="{{ $href }}" class="nm">{{ $mwPName }}</a>
        @else
            <div class="nm">{{ $mwPName }}</div>
        @endif
        @if (! empty($sub))<div class="sb">{{ $sub }}</div>@endif
    </div>
</div>
