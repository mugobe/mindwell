@extends('layouts.app')
@section('title', 'Payments')
@section('content')
{{-- Added 2026-10-02 — see PaymentHistoryController. --}}

<div class="dash-head" style="align-items:center;">
    <div style="display:flex;align-items:center;gap:12px;">
        @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6M9 16h3"/>'])
        <h1>Payments</h1>
    </div>
    <a href="{{ route('subscription.index') }}" class="btn ghost sm" style="border-radius:999px;">My plan</a>
</div>

<div class="card" style="margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <div style="font-size:12px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;">Paid in {{ now()->format('Y') }}</div>
        <div style="font-size:24px;font-weight:800;color:var(--ink-900);margin-top:2px;">{{ $currency }} {{ number_format($paidThisYear, 0) }}</div>
    </div>
    <p style="font-size:12px;color:var(--ink-500);max-width:360px;margin:0;">
        Every payment gets a receipt by email, and you can download a PDF copy here any time.
        Sessions covered by your plan or an employer benefit aren't listed, as nothing was charged.
    </p>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Date</th>
                <th>What for</th>
                <th>Amount</th>
                <th>Status</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                @php
                    // A charge still "pending" after an hour was never approved on the phone.
                    $stale = $payment->status === 'pending' && $payment->created_at->lt(now()->subHour());
                    [$pillClass, $pillLabel] = match (true) {
                        $payment->isRefunded() => ['warn', $payment->isFullyRefunded() ? 'Refunded' : 'Part refunded'],
                        $payment->status === 'successful' => ['success', 'Paid'],
                        $payment->status === 'pending' && ! $stale => ['warn', 'Waiting for approval'],
                        default => ['danger', 'Not completed'],
                    };
                @endphp
                <tr>
                    <td style="padding-left:16px;white-space:nowrap;">{{ $payment->created_at->format('M j, Y') }}</td>
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $payment->receiptDescription() }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">
                            {{ $payment->method_label ?? 'Mobile Money' }}{{ $payment->receipt_number ? ' · '.$payment->receipt_number : '' }}
                        </div>
                    </td>
                    <td style="white-space:nowrap;font-weight:700;">
                        {{ $payment->currency }} {{ number_format((float) $payment->amount, 0) }}
                        @if ($payment->hasDiscount())
                            <div style="font-size:11.5px;font-weight:600;color:var(--ink-500);">Voucher {{ $payment->voucher?->code }} saved {{ number_format((float) $payment->discount_amount, 0) }}</div>
                        @endif
                        @if ($payment->isRefunded())
                            <div style="font-size:11.5px;font-weight:600;color:var(--ink-500);">{{ number_format((float) $payment->refund_amount, 0) }} refunded {{ $payment->refunded_at->format('j M') }}</div>
                        @endif
                    </td>
                    <td style="white-space:nowrap;"><span class="pill {{ $pillClass }}">{{ $pillLabel }}</span></td>
                    <td style="padding-right:16px;text-align:right;white-space:nowrap;">
                        @if ($payment->status === 'successful')
                            <a href="{{ route('payments.receipt', $payment) }}" class="btn ghost sm" style="border-radius:999px;">Receipt (PDF)</a>
                        @elseif ($payment->status === 'pending' && ! $stale)
                            <a href="{{ route('payments.flutterwave.waiting', $payment) }}" class="btn primary sm" style="border-radius:999px;">Check</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="padding:28px 16px;text-align:center;color:var(--ink-500);">
                        No payments yet. When you pay for a session or a plan, it will show up here with its receipt.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $payments->links() }}</div>
@endsection
