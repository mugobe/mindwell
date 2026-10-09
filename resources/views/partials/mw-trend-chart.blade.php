{{--
    Small single-series trend chart (added 2026-10-03 for progress pages). Needs
    partials.mw-trend-chart-styles included once on the page.

    @include('partials.mw-trend-chart', [
        'points' => [['at' => Carbon, 'y' => 12, 'tip' => 'Oct 2: moderate', 'flag' => false], ...],
        'yMin' => 0, 'yMax' => 27,
        'grid' => [0 => '', 5 => 'mild', 10 => 'moderate'],   // value => label on the right
        'color' => '#139080',
        'label' => 'Low mood over time',                      // for screen readers
        'height' => 150,                                      // optional
    ])

    One series per chart, one y-axis: two measures on different scales get two charts
    (small multiples), never a shared or dual axis. Points sit at their real dates.
--}}
@php
    $mwPts = collect($points)->values();
    $mwN = $mwPts->count();
    $mwRange = max(1, $yMax - $yMin);
    // Keep dots off the very top/bottom edge so the 2px ring + hover target fit.
    $mwY = fn ($v) => round(6 + (1 - (($v - $yMin) / $mwRange)) * 88, 2);
    $mwT0 = $mwN ? $mwPts->first()['at']->getTimestamp() : 0;
    $mwSpan = max(1, ($mwN ? $mwPts->last()['at']->getTimestamp() : 0) - $mwT0);
    $mwXY = $mwPts->map(fn ($p) => $p + [
        'px' => $mwN === 1 ? 50 : round(3 + (($p['at']->getTimestamp() - $mwT0) / $mwSpan) * 94, 2),
        'py' => $mwY($p['y']),
    ]);
    $mwPoly = $mwXY->map(fn ($p) => ($p['px'] * 10).','.$p['py'])->implode(' ');
@endphp
<div class="mw-tc" style="--mw-tc-h:{{ $height ?? 150 }}px;">
    @if ($mwN === 0)
        <div class="mw-tc-empty">Nothing to show yet.</div>
    @else
        <div class="mw-tc-plot" role="img" aria-label="{{ $label }}">
            @foreach ($grid as $gValue => $gLabel)
                <div class="mw-tc-grid {{ $gValue == $yMin ? 'base' : '' }}" style="top:{{ $mwY($gValue) }}%;"><span>{{ $gLabel }}</span></div>
            @endforeach
            @if ($mwN > 1)
                <svg viewBox="0 0 1000 100" preserveAspectRatio="none" aria-hidden="true">
                    <polyline points="{{ $mwPoly }}" fill="none" stroke="{{ $color }}" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/>
                </svg>
            @endif
            @foreach ($mwXY as $p)
                <span class="mw-tc-dot {{ $p['px'] > 75 ? 'right' : ($p['px'] < 25 ? 'left' : '') }} {{ ! empty($p['flag']) ? 'flag' : '' }}" tabindex="0" style="left:{{ $p['px'] }}%;top:{{ $p['py'] }}%;background:{{ $color }};">
                    <span class="mw-tc-tip">{{ $p['tip'] }}</span>
                </span>
            @endforeach
        </div>
        <div class="mw-tc-x">
            <span>{{ $mwPts->first()['at']->format('M j') }}</span>
            @if ($mwN > 1)<span>{{ $mwPts->last()['at']->format('M j') }}</span>@endif
        </div>
    @endif
</div>
