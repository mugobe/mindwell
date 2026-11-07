{{-- Mood face 1 (very low) to 5 (great), same drawing as the home page and the app.
     Usage: @include('partials.mood-face', ['mood' => 4, 'size' => 32]) --}}
@php
    $mwF = [
        1 => ['#E5484D', 'M8.5 16.5c1-1.4 2.2-2 3.5-2s2.5.6 3.5 2'],
        2 => ['#F2994A', 'M9 16c.9-.8 1.9-1.2 3-1.2s2.1.4 3 1.2'],
        3 => ['#E3B341', 'M9 15.5h6'],
        4 => ['#3FB984', 'M9 14.5c.9.9 1.9 1.4 3 1.4s2.1-.5 3-1.4'],
        5 => ['#0E9F6E', 'M8.5 14c1 1.6 2.2 2.4 3.5 2.4s2.5-.8 3.5-2.4'],
    ][(int) ($mood ?? 3)] ?? ['#98A5A1', 'M9 15.5h6'];
    $mwFs = $size ?? 32;
@endphp
<svg viewBox="0 0 24 24" width="{{ $mwFs }}" height="{{ $mwFs }}" aria-hidden="true" style="flex-shrink:0;display:block;">
    <circle cx="12" cy="12" r="10.5" fill="{{ $mwF[0] }}" opacity=".18"/>
    <circle cx="12" cy="12" r="10.5" fill="none" stroke="{{ $mwF[0] }}" stroke-width="1.5"/>
    <circle cx="9" cy="10" r="1.2" fill="{{ $mwF[0] }}"/><circle cx="15" cy="10" r="1.2" fill="{{ $mwF[0] }}"/>
    <path d="{{ $mwF[1] }}" fill="none" stroke="{{ $mwF[0] }}" stroke-width="1.6" stroke-linecap="round"/>
</svg>
