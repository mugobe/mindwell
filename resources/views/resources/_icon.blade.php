{{-- Kind icon tile for a resource. Needs $kind; optional $size (default 40). --}}
@php
    $mwRk = [
        'article' => ['#E6EFF9', '#034C8F', '<path d="M6 3.5h9l3 3V20H6z"/><path d="M9 10h6M9 13.5h6M9 17h4"/>'],
        'pdf' => ['#FDE7E7', '#B42318', '<path d="M6 3.5h9l3 3V20H6z"/><path d="M14.5 3.5V7H18"/><path d="M9 13h6M9 16.5h4"/>'],
        'audio' => ['#F1E8FB', '#7A4CC2', '<path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/>'],
        'video' => ['#FFEBD6', '#C25E00', '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/>'],
        'link' => ['#DDF5EC', '#0E7A5A', '<path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/>'],
    ][$kind] ?? ['#E6EFF9', '#034C8F', '<path d="M6 3.5h9l3 3V20H6z"/>'];
    $mwRs = $size ?? 40;
@endphp
<span aria-hidden="true" style="width:{{ $mwRs }}px;height:{{ $mwRs }}px;flex:0 0 {{ $mwRs }}px;border-radius:{{ round($mwRs * .3) }}px;background:{{ $mwRk[0] }};color:{{ $mwRk[1] }};display:inline-flex;align-items:center;justify-content:center;">
    <svg viewBox="0 0 24 24" width="{{ round($mwRs * .5) }}" height="{{ round($mwRs * .5) }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $mwRk[2] !!}</svg>
</span>
