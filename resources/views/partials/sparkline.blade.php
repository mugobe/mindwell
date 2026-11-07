{{-- Small trend line for number tiles (2026-11-03).
     @include('partials.sparkline', ['points' => [1, 3, 2, 5], 'color' => '#034C8F']) --}}
@php
    $mwSp = array_values(array_map('floatval', array_filter((array) ($points ?? []), fn ($v) => $v !== null)));
    $mwSw = (int) ($w ?? 84);
    $mwSh = (int) ($h ?? 28);
@endphp
@if (count($mwSp) >= 2)
    @php
        $mwMax = max($mwSp); $mwMin = min($mwSp); $mwRng = ($mwMax - $mwMin) ?: 1;
        $mwStep = $mwSw / (count($mwSp) - 1);
        $mwPts = collect($mwSp)->map(fn ($p, $i) => round($i * $mwStep, 1).','.round($mwSh - 3 - ($p - $mwMin) / $mwRng * ($mwSh - 6), 1))->join(' ');
    @endphp
    <svg class="spark" width="{{ $mwSw }}" height="{{ $mwSh }}" viewBox="0 0 {{ $mwSw }} {{ $mwSh }}" aria-hidden="true">
        <polygon points="0,{{ $mwSh }} {{ $mwPts }} {{ $mwSw }},{{ $mwSh }}" fill="{{ $color ?? '#034C8F' }}" opacity=".10"/>
        <polyline points="{{ $mwPts }}" fill="none" stroke="{{ $color ?? '#034C8F' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
@endif
