{{--
    Reusable soft pastel-blob illustration for client-facing empty/status states,
    added 2026-09-27 as part of the "warmer, more colorful client portal" design pass
    (reference: Google Meet's home screen — layered pastel shapes + a simple line icon).

    Usage: @include('partials.mw-illustration', ['variant' => 'sprout', 'size' => 128])
    Variants: sprout (no intake yet), hourglass (session in progress),
              again (rebook a past therapist), compass (browse therapists),
              calendar (no open slots), clipboard (no sessions yet).

    Deliberately built from inline SVG primitives (ellipses + simple line icons) rather
    than external image assets, so it stays a single dependency-free file and can be
    recolored just by changing the --mw-* custom properties defined in layouts/app.blade.php.
--}}
@php
    // Every fill carries a hardcoded hex fallback as the var()'s second argument
    // (matching the --mw-* values defined in layouts/app.blade.php) so this illustration
    // never renders solid black even if that stylesheet ever fails to load or apply —
    // learned 2026-09-27 after exactly that happened during testing.
    $mwIllustrations = [
        'sprout' => [
            'blobs' => [
                ['cx' => 62, 'cy' => 96, 'rx' => 54, 'ry' => 42, 'fill' => 'var(--mw-sage, #DEE8CE)', 'rotate' => -8],
                ['cx' => 106, 'cy' => 56, 'rx' => 30, 'ry' => 25, 'fill' => 'var(--mw-coral-tint, #FCE1D3)', 'rotate' => 14],
            ],
            'dot' => ['cx' => 132, 'cy' => 32, 'fill' => 'var(--mw-gold, #F6E1A8)'],
            'icon' => '<path d="M80 112V74"/><path d="M80 86c0-15-11-24-25-24 0 15 11 25 25 24z"/><path d="M80 78c0-12 9-20 20-20 0 12-9 20-20 20z"/>',
        ],
        'hourglass' => [
            'blobs' => [
                ['cx' => 62, 'cy' => 96, 'rx' => 54, 'ry' => 42, 'fill' => 'var(--mw-teal-tint, #DCEDE8)', 'rotate' => -6],
                ['cx' => 106, 'cy' => 56, 'rx' => 30, 'ry' => 25, 'fill' => 'var(--mw-blush, #F5DCE2)', 'rotate' => 10],
            ],
            'dot' => ['cx' => 132, 'cy' => 32, 'fill' => 'var(--mw-gold, #F6E1A8)'],
            'icon' => '<circle cx="80" cy="88" r="27"/><path d="M80 75v15l11 7"/><path d="M70 53h20"/>',
        ],
        'again' => [
            'blobs' => [
                ['cx' => 62, 'cy' => 96, 'rx' => 54, 'ry' => 42, 'fill' => 'var(--mw-coral-tint, #FCE1D3)', 'rotate' => -8],
                ['cx' => 106, 'cy' => 56, 'rx' => 30, 'ry' => 25, 'fill' => 'var(--mw-sage, #DEE8CE)', 'rotate' => 12],
            ],
            'dot' => ['cx' => 132, 'cy' => 32, 'fill' => 'var(--mw-teal-dot, #6FA99B)'],
            'icon' => '<path d="M63 81a19 19 0 0132-14"/><path d="M95 60v10H85"/><path d="M97 95a19 19 0 01-32 14"/><path d="M65 116v-10h10"/>',
        ],
        'compass' => [
            'blobs' => [
                ['cx' => 62, 'cy' => 96, 'rx' => 54, 'ry' => 42, 'fill' => 'var(--mw-gold, #F6E1A8)', 'rotate' => -8],
                ['cx' => 106, 'cy' => 56, 'rx' => 30, 'ry' => 25, 'fill' => 'var(--mw-teal-tint, #DCEDE8)', 'rotate' => 12],
            ],
            'dot' => ['cx' => 132, 'cy' => 32, 'fill' => 'var(--mw-blush, #F5DCE2)'],
            'icon' => '<circle cx="80" cy="88" r="27"/><path d="M90 75l-8 19-19 8 8-19z"/>',
        ],
        // No open slots on a therapist's booking page.
        'calendar' => [
            'blobs' => [
                ['cx' => 62, 'cy' => 96, 'rx' => 54, 'ry' => 42, 'fill' => 'var(--mw-teal-tint, #DCEDE8)', 'rotate' => -8],
                ['cx' => 106, 'cy' => 56, 'rx' => 30, 'ry' => 25, 'fill' => 'var(--mw-gold, #F6E1A8)', 'rotate' => 12],
            ],
            'dot' => ['cx' => 132, 'cy' => 32, 'fill' => 'var(--mw-coral-tint, #FCE1D3)'],
            'icon' => '<rect x="55" y="65" width="50" height="45" rx="6"/><path d="M55 80h50"/><path d="M67 58v14M93 58v14"/>',
        ],
        // No sessions yet on the "My sessions" history page.
        'clipboard' => [
            'blobs' => [
                ['cx' => 62, 'cy' => 96, 'rx' => 54, 'ry' => 42, 'fill' => 'var(--mw-sage, #DEE8CE)', 'rotate' => -8],
                ['cx' => 106, 'cy' => 56, 'rx' => 30, 'ry' => 25, 'fill' => 'var(--mw-coral-tint, #FCE1D3)', 'rotate' => 12],
            ],
            'dot' => ['cx' => 132, 'cy' => 32, 'fill' => 'var(--mw-teal-dot, #6FA99B)'],
            'icon' => '<rect x="60" y="58" width="40" height="55" rx="6"/><rect x="72" y="52" width="16" height="10" rx="3"/><path d="M68 78h24M68 90h24M68 102h16"/>',
        ],
    ];

    $mwVariant = $mwIllustrations[$variant ?? 'sprout'] ?? $mwIllustrations['sprout'];
    $mwSize = $size ?? 128;
@endphp
<svg viewBox="0 0 160 160" style="width:{{ $mwSize }}px;height:{{ $mwSize }}px;" aria-hidden="true">
    @foreach ($mwVariant['blobs'] as $mwBlob)
        <ellipse
            cx="{{ $mwBlob['cx'] }}" cy="{{ $mwBlob['cy'] }}"
            rx="{{ $mwBlob['rx'] }}" ry="{{ $mwBlob['ry'] }}"
            fill="{{ $mwBlob['fill'] }}"
            transform="rotate({{ $mwBlob['rotate'] }} {{ $mwBlob['cx'] }} {{ $mwBlob['cy'] }})"
        />
    @endforeach
    <circle cx="{{ $mwVariant['dot']['cx'] }}" cy="{{ $mwVariant['dot']['cy'] }}" r="7" fill="{{ $mwVariant['dot']['fill'] }}" />
    <g fill="none" stroke="var(--teal-600, #2f6f66)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        {!! $mwVariant['icon'] !!}
    </g>
</svg>
