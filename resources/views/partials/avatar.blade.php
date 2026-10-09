{{-- Coloured initials bubble (2026-10-08). Same name → same colour everywhere.
     @include('partials.avatar', ['name' => $user->name, 'size' => 28]) --}}
@php
    $mwAvN = trim((string) ($name ?? ''));
    $mwAvS = (int) ($size ?? 32);
    $mwAvI = collect(preg_split('/\s+/u', $mwAvN))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('') ?: '•';
@endphp
<span class="mw-av c{{ crc32($mwAvN) % 5 }}" aria-hidden="true"@if ($mwAvS !== 32) style="width:{{ $mwAvS }}px;height:{{ $mwAvS }}px;flex-basis:{{ $mwAvS }}px;font-size:{{ round($mwAvS * 0.36, 1) }}px;"@endif>{{ $mwAvI }}</span>
