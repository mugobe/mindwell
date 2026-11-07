@extends('layouts.app')
@section('title', 'Payments')
@section('content')
{{-- Added 2026-10-02, see PaymentHistoryController. 2026-11-01 refresh: list instead of a table. --}}

<style>
    .mwpay-top { display:grid; grid-template-columns:minmax(0,320px) minmax(0,1fr); gap:16px; margin-bottom:22px; }
    .mwpay-total { padding:22px; border-radius:22px; color:#fff; background:radial-gradient(circle at 100% 0, rgba(0,184,136,.45), transparent 50%), linear-gradient(135deg,#022F5C,#034C8F 60%,#0B5FB0); }
    .mwpay-total .k { font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; opacity:.75; }
    .mwpay-total .v { font-size:30px; font-weight:800; letter-spacing:-.02em; margin-top:6px; }
    .mwpay-row { display:flex; align-items:center; gap:14px; padding:16px 0; }
    .mwpay-row + .mwpay-row { border-top:1px solid rgba(3,40,80,.08); }
    .mwpay-row .ic { width:42px; height:42px; flex:0 0 42px; border-radius:13px; display:flex; align-items:center; justify-content:center; background:var(--mw-navy-tint, #E6EFF9); color:var(--teal-700); }
    .mwpay-row .ic.paid { background:var(--mw-green-tint, #DDF5EC); color:var(--mw-green, #00A87C); }
    .mwpay-row .ic.warn { background:#FFF1E0; color:#B25C00; }
    .mwpay-row .ic.bad { background:#FDECEC; color:#B3261E; }
    .mwpay-row .ic svg { width:20px; height:20px; }
    .mwpay-row .what { flex:1; min-width:0; }
    .mwpay-row .t { font-size:14.5px; font-weight:700; color:var(--ink-900); }
    .mwpay-row .s { font-size:12.5px; color:var(--ink-500); margin-top:2px; }
    .mwpay-row .amt { text-align:right; white-space:nowrap; }
    .mwpay-row .amt b { display:block; font-size:15px; color:var(--ink-900); }
    .mwpay-row .act { display:flex; gap:8px; }
    @media (max-width: 760px) {
        .mwpay-top { grid-template-columns:1fr; }
        .mwpay-row { flex-wrap:wrap; }
        .mwpay-row .what { flex-basis:calc(100% - 60px); }
        .mwpay-row .amt { text-align:left; margin-left:56px; }
        .mwpay-row .act { margin-left:auto; }
    }
</style>

<div class="dash-head">
    <div style="display:flex;align-items:center;gap:12px;margin-right:auto;">
        @include('partials.mw-icon-badge', ['size' => 44, 'margin' => '0', 'tint' => 'var(--mw-gold, #FDEBC8)', 'icon' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6M9 16h3"/>'])
        <div>
            <h1>Payments</h1>
            <div class="mwc-sub" style="margin-top:2px;">Everything you have paid, with a receipt for each.</div>
        </div>
    </div>
    <a href="{{ route('subscription.index') }}" class="btn ghost sm" style="border-radius:999px;">My plan</a>
</div>

<div class="mwpay-top">
    <div class="mwpay-total">
        <div class="k">Paid in {{ now()->format('Y') }}</div>
        <div class="v">{{ $currency }} {{ number_format($paidThisYear, 0) }}</div>
    </div>
    <div class="mw-note info top" style="margin:0;">
        <div class="mw-note-body">
            <div class="mw-note-title">Receipts</div>
            <div class="mw-note-text">Every payment gets a receipt by email, and you can download a PDF copy here at any time. Sessions covered by your plan or an employer benefit are not listed, because nothing was charged.</div>
        </div>
    </div>
</div>

<h2 class="mwc-h2">Payment history</h2>
<div class="card" style="padding:4px 20px;">
    @forelse ($payments as $payment)
        @php
            // A charge still "pending" after an hour was never approved on the phone.
            $stale = $payment->status === 'pending' && $payment->created_at->lt(now()->subHour());
            [$pillClass, $pillLabel, $icClass] = match (true) {
                $payment->isRefunded() => ['warn', $payment->isFullyRefunded() ? 'Refunded' : 'Part refunded', 'warn'],
                $payment->status === 'successful' => ['success', 'Paid', 'paid'],
                $payment->status === 'pending' && ! $stale => ['warn', 'Waiting for approval', 'warn'],
                default => ['danger', 'Not completed', 'bad'],
            };
        @endphp
        <div class="mwpay-row">
            <span class="ic {{ $icClass }}" aria-hidden="true">
                @if ($icClass === 'paid')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>
                @elseif ($icClass === 'bad')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7l10 10M17 7L7 17"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                @endif
            </span>
            <div class="what">
                <div class="t">{{ $payment->receiptDescription() }}</div>
                <div class="s">
                    {{ $payment->created_at->format('j M Y') }}, {{ $payment->method_label ?? 'Mobile Money' }}{{ $payment->receipt_number ? ', '.$payment->receipt_number : '' }}
                </div>
                @if ($payment->hasDiscount())
                    <div class="s">Voucher {{ $payment->voucher?->code }} saved {{ number_format((float) $payment->discount_amount, 0) }}</div>
                @endif
                @if ($payment->isRefunded())
                    <div class="s">{{ number_format((float) $payment->refund_amount, 0) }} refunded on {{ $payment->refunded_at->format('j M') }}</div>
                @endif
            </div>
            <div class="amt">
                <b>{{ $payment->currency }} {{ number_format((float) $payment->amount, 0) }}</b>
                <span class="pill {{ $pillClass }}" style="margin-top:4px;">{{ $pillLabel }}</span>
            </div>
            <div class="act">
                @if ($payment->status === 'successful')
                    <a href="{{ route('payments.receipt', $payment) }}" class="mw-pill-btn">Receipt</a>
                @elseif ($payment->status === 'pending' && ! $stale)
                    <a href="{{ route('payments.waiting', $payment) }}" class="mw-pill-btn filled">Check</a>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align:center;padding:30px 10px;">
            @include('partials.mw-illustration', ['variant' => 'clipboard', 'size' => 100])
            <div style="font-size:15px;font-weight:800;color:var(--ink-900);margin-top:8px;">No payments yet</div>
            <p style="font-size:13.5px;color:var(--ink-500);margin:4px 0 0;">When you pay for a session or a plan, it will show up here with its receipt.</p>
        </div>
    @endforelse
</div>

@include('partials.pager', ['paginator' => $payments])
@endsection
