{{-- Voucher code box for checkout pages (2026-10-07, roadmap 25). Needs $quote from
     App\Services\Vouchers::quote(). Applying reloads the page with ?voucher=CODE so the
     client sees the new price before paying; the pay form re-checks it (see
     partials.voucher-hidden). --}}
@php
    $mwCur = config('flutterwave.currency');
    $mwHere = url()->current();
@endphp
<div style="margin:-8px 0 20px;text-align:left;">
    @if ($quote['applied'])
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;background:var(--mw-sage, #DEE8CE);border-radius:12px;padding:10px 14px;font-size:13px;color:var(--ink-900);">
            <div>
                <strong>{{ $quote['code'] }}</strong> applied — {{ $quote['voucher']->discountLabel() }}
                <div style="font-size:12px;color:var(--ink-700);">
                    {{ $mwCur }} {{ number_format($quote['original'], 0) }} − {{ number_format($quote['discount'], 0) }} =
                    <strong>{{ $mwCur }} {{ number_format($quote['total'], 0) }}</strong>
                </div>
            </div>
            <a href="{{ $mwHere }}" style="font-size:12px;font-weight:700;color:var(--ink-700);">Remove</a>
        </div>
    @else
        <details {{ $quote['code'] ? 'open' : '' }}>
            <summary style="font-size:12.5px;font-weight:700;color:var(--teal-600, #2f6f66);cursor:pointer;list-style:none;">Have a voucher code?</summary>
            <form method="GET" action="{{ $mwHere }}" style="display:flex;gap:8px;margin-top:8px;">
                <input class="mw-field" name="voucher" value="{{ $quote['code'] }}" placeholder="e.g. WELCOME20" maxlength="40" autocomplete="off" style="flex:1;text-transform:uppercase;">
                <button type="submit" class="btn ghost sm">Apply</button>
            </form>
            @if ($quote['error'])
                <div class="mw-alert danger" style="margin-top:8px;">{{ $quote['error'] }}</div>
            @endif
        </details>
    @endif
</div>
