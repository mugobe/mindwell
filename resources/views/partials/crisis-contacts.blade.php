{{--
    Who to call right now (Phase 0, 2026-10-05). One place for every crisis screen:
    the public "I need urgent help" page, the intake crisis screen and the check-in
    crisis screen. Numbers live in config/mindwell.php ('crisis'):
     - the 24/7 line Dr. Ntabade chooses (MINDWELL_CRISIS_LINE_*), shown first when set;
     - Uganda's national emergency number 999 / 112 (always);
     - Mental Health Uganda's toll-free line, with its real hours (weekdays only).
--}}
@php $mwCrisis = config('mindwell.crisis'); @endphp
<div style="display:flex;flex-direction:column;gap:10px;text-align:left;">
    @if (! empty($mwCrisis['line_number']))
        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $mwCrisis['line_number']) }}" class="btn" style="background:var(--danger, #c0392b);color:#fff;display:block;text-align:center;padding:14px 18px;border-radius:14px;">
            <span style="display:block;font-size:15px;font-weight:800;">Call {{ $mwCrisis['line_name'] ?: 'the crisis line' }} — {{ $mwCrisis['line_number'] }}</span>
            <span style="display:block;font-size:12px;font-weight:600;opacity:.9;">{{ $mwCrisis['line_hours'] }}</span>
        </a>
    @endif

    <a href="tel:112" class="btn" style="background:{{ empty($mwCrisis['line_number']) ? 'var(--danger, #c0392b)' : '#fff' }};color:{{ empty($mwCrisis['line_number']) ? '#fff' : 'var(--danger, #c0392b)' }};border:2px solid var(--danger, #c0392b);display:block;text-align:center;padding:14px 18px;border-radius:14px;">
        <span style="display:block;font-size:15px;font-weight:800;">In immediate danger? Call 999 or 112</span>
        <span style="display:block;font-size:12px;font-weight:600;opacity:.9;">Uganda's emergency number · free · 24 hours, every day</span>
    </a>

    <a href="tel:+256800212121" class="btn ghost" style="display:block;text-align:center;padding:12px 18px;border-radius:14px;">
        <span style="display:block;font-size:14px;font-weight:800;">Mental Health Uganda — 0800 21 21 21</span>
        <span style="display:block;font-size:12px;font-weight:600;color:var(--ink-500);">Toll-free · Monday to Friday, 8:30am – 5:00pm</span>
    </a>

    <div style="font-size:12.5px;color:var(--ink-700);line-height:1.6;background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:12px 14px;">
        You can also go straight to the emergency department of your nearest hospital, or ask someone you trust to stay
        with you. If you're thinking about ending your life, please tell someone now — you don't have to go through this alone.
    </div>
</div>
