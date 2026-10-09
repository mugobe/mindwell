<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt {{ $transaction->receipt_number }}</title>
<style>
    /* dompdf understands a subset of CSS 2.1 — plain blocks and tables only, same as
       reports/pdf.blade.php. Added 2026-10-02 — see App\Services\Receipts. */
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 36px 40px; }
    table { width: 100%; border-collapse: collapse; }
    .brand { font-size: 22px; font-weight: bold; color: #1f7a6c; }
    .biz { font-size: 10.5px; color: #777; line-height: 1.5; }
    .title { font-size: 26px; font-weight: bold; color: #222; text-align: right; letter-spacing: 0.02em; }
    .meta td { padding: 3px 0; font-size: 11px; }
    .meta .k { color: #999; text-align: right; padding-right: 10px; }
    .meta .v { text-align: right; font-weight: bold; width: 1%; white-space: nowrap; }
    .label { font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.04em; color: #999; margin-bottom: 3px; }
    .billto { margin: 26px 0 22px; }
    .billto .name { font-size: 13px; font-weight: bold; }
    .items th { font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.04em; color: #999; text-align: left; border-bottom: 1.5px solid #222; padding: 0 0 6px; }
    .items td { padding: 12px 0; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
    .items .amt { text-align: right; white-space: nowrap; }
    .total td { padding: 12px 0 0; font-size: 14px; font-weight: bold; }
    .paid { display: inline-block; margin-top: 22px; padding: 5px 14px; border: 2px solid #1f7a6c; color: #1f7a6c; font-weight: bold; font-size: 12px; letter-spacing: 0.08em; }
    .test { margin-top: 18px; padding: 8px 12px; background: #fff4d6; color: #8a5a00; font-size: 10.5px; }
    .foot { margin-top: 40px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 9.5px; color: #999; line-height: 1.6; }
</style>
</head>
<body>
    @php
        $paidAt = $transaction->paidAt() ?? $transaction->created_at;
        $client = $transaction->clientProfile?->user;
        $amount = $transaction->currency.' '.number_format((float) $transaction->amount, 0);
    @endphp

    <table>
        <tr>
            <td style="vertical-align:top;">
                <div class="brand">{{ $business['business_name'] ?? 'Mindwell' }}</div>
                <div class="biz">
                    @if (! empty($business['address'])){{ $business['address'] }}<br>@endif
                    @if (! empty($business['phone'])){{ $business['phone'] }}<br>@endif
                    @if (! empty($business['email'])){{ $business['email'] }}<br>@endif
                    @if (! empty($business['tin']))TIN: {{ $business['tin'] }}@endif
                </div>
            </td>
            <td style="vertical-align:top;width:45%;">
                <div class="title">RECEIPT</div>
                <table class="meta" style="margin-top:8px;">
                    <tr><td class="k">Receipt no.</td><td class="v">{{ $transaction->receipt_number }}</td></tr>
                    <tr><td class="k">Date paid</td><td class="v">{{ $paidAt->format('M j, Y') }}</td></tr>
                    <tr><td class="k">Payment method</td><td class="v">{{ $transaction->method_label ?? 'Mobile Money' }}</td></tr>
                    <tr><td class="k">Reference</td><td class="v">{{ $transaction->tx_ref }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="billto">
        <div class="label">Received from</div>
        <div class="name">{{ $client?->name ?? 'Client' }}</div>
        @if ($client?->email)<div class="biz">{{ $client->email }}</div>@endif
    </div>

    <table class="items">
        <tr>
            <th>Description</th>
            <th class="amt" style="text-align:right;">Amount</th>
        </tr>
        @if ($transaction->hasDiscount())
            {{-- Vouchers (2026-10-07). --}}
            <tr>
                <td>{{ $transaction->receiptDescription() }}</td>
                <td class="amt">{{ $transaction->currency }} {{ number_format((float) $transaction->original_amount, 0) }}</td>
            </tr>
            <tr>
                <td>Voucher {{ $transaction->voucher?->code }}</td>
                <td class="amt">− {{ $transaction->currency }} {{ number_format((float) $transaction->discount_amount, 0) }}</td>
            </tr>
        @else
            <tr>
                <td>{{ $transaction->receiptDescription() }}</td>
                <td class="amt">{{ $amount }}</td>
            </tr>
        @endif
        <tr class="total">
            <td style="text-align:right;padding-right:16px;">Total paid</td>
            <td class="amt">{{ $amount }}</td>
        </tr>
    </table>

    <div class="paid">PAID</div>

    @if ($transaction->isRefunded())
        {{-- Added 2026-10-06. --}}
        <div class="test" style="background:#fdecec;color:#9b2c2c;">
            REFUNDED: {{ $transaction->currency }} {{ number_format((float) $transaction->refund_amount, 0) }} on {{ $transaction->refunded_at->format('M j, Y') }}.
            Net paid: {{ $transaction->currency }} {{ number_format($transaction->netAmount(), 0) }}.
        </div>
    @endif

    @if ($transaction->isTest())
        <div class="test">TEST PAYMENT — made against the Flutterwave sandbox. No real money was taken.</div>
    @endif

    <div class="foot">
        @if ($transaction->isVoucherFree())
            Thank you. This receipt confirms a session or plan fully covered by a Mindwell voucher — no payment was taken.
        @elseif ($transaction->isCash())
            Thank you. This receipt confirms a cash payment received and recorded by the Mindwell team.
        @else
            Thank you. This receipt confirms a payment made through Mindwell by Mobile Money, processed by Flutterwave.
        @endif
        Keep it for your records — for example to claim the cost back from an employer or insurer.
        Questions about this payment? Reply to your receipt email{{ ! empty($business['email']) ? ' or write to '.$business['email'] : '' }}.
    </div>
</body>
</html>
