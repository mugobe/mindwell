{{--
    Small pastel-circle icon badge, added 2026-09-27 as part of rolling the "warmer,
    more colorful client portal" design pass (started on the dashboard) out across the
    rest of the client-facing pages.

    Same idea as mw-illustration.blade.php but lighter-weight: a single 24x24 line icon
    (reusing icons already drawn elsewhere in the app, e.g. the admin sidebar) sitting in
    a soft --mw-* tinted circle. Used on confirmation/status pages (checkout, subscribe,
    connected, pending-approval) and next to page headings, where a full two-blob
    illustration would be too much for a single-line heading.

    Usage: @include('partials.mw-icon-badge', ['tint' => 'var(--mw-sage, #DEE8CE)', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>'])

    As with mw-illustration, the icon stroke color carries a hardcoded hex fallback so it
    never silently renders black if the --teal-600 variable (defined in the real app.css
    this sandbox has never had visible contents for) isn't present for some reason.
--}}
@php
    $mwBadgeSize = $size ?? 56;
    $mwBadgeTint = $tint ?? 'var(--mw-teal-tint, #DCEDE8)';
    $mwIconSize = round($mwBadgeSize * 0.46);
    // Stacked above a centered heading wants breathing room below; sitting inline next
    // to a heading (pass margin => '0') wants none.
    $mwBadgeMargin = $margin ?? '14px';
@endphp
<span style="display:inline-flex;align-items:center;justify-content:center;width:{{ $mwBadgeSize }}px;height:{{ $mwBadgeSize }}px;border-radius:50%;background:{{ $mwBadgeTint }};margin-bottom:{{ $mwBadgeMargin }};flex-shrink:0;">
    <svg viewBox="0 0 24 24" fill="none" stroke="var(--teal-600, #2f6f66)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:{{ $mwIconSize }}px;height:{{ $mwIconSize }}px;">
        {!! $icon !!}
    </svg>
</span>
