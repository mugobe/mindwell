<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt {{ $transaction->receipt_number }}</title>
<style>
    /* Hospital-style official receipt (2026-11-01, Collins): logo + practice block,
       OFFICIAL RECEIPT title, client block, numbered item table with amount in words,
       "received by" footer. dompdf understands plain blocks and tables only.
       Helvetica (Arial-like) is built into dompdf, so no font files are needed.
       Keep to Latin-1 characters in this file: Helvetica can't draw "−", "·" or "—". */
    @page { margin: 28px 34px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .navy { color: #034C8F; }
    .head td { vertical-align: top; }
    .practice { text-align: right; font-size: 10px; line-height: 1.55; color: #333; }
    .practice .name { font-size: 13px; font-weight: bold; color: #034C8F; text-transform: uppercase; letter-spacing: 0.02em; margin-bottom: 2px; }
    .rule { border-top: 2.5px solid #034C8F; margin: 12px 0 0; }
    .rule-thin { border-top: 1px solid #00B888; margin: 2px 0 14px; }
    .title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 0.18em; color: #034C8F; margin: 0 0 12px; }
    .meta td { font-size: 10.5px; padding: 2px 0; }
    .meta .k { color: #555; width: 22%; }
    .meta .v { font-weight: bold; }
    .box { border: 1px solid #b9c3cf; margin-top: 12px; }
    .box td { padding: 6px 8px; font-size: 10.5px; border: 1px solid #d6dde5; vertical-align: top; }
    .box .k { background: #f2f5f8; color: #444; width: 18%; font-weight: bold; }
    .items { margin-top: 14px; }
    .items th { background: #034C8F; color: #fff; font-size: 10px; font-weight: bold; text-align: left; padding: 7px 8px; border: 1px solid #034C8F; }
    .items td { padding: 8px; border: 1px solid #d6dde5; font-size: 10.5px; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .c { text-align: center; }
    .sum td { border: 1px solid #d6dde5; padding: 6px 8px; font-size: 10.5px; }
    .sum .label { text-align: right; color: #444; }
    .sum .grand td { font-size: 12.5px; font-weight: bold; background: #f2f5f8; color: #034C8F; }
    .words { margin-top: 10px; font-size: 10.5px; }
    .words b { color: #034C8F; }
    .status { margin-top: 14px; }
    .stamp { display: inline-block; border: 2px solid #00875f; color: #00875f; padding: 4px 14px; font-weight: bold; font-size: 12px; letter-spacing: 0.15em; }
    .note { margin-top: 10px; padding: 7px 10px; border: 1px solid #d6dde5; background: #f7f9fb; font-size: 10px; color: #333; }
    .sign td { font-size: 10px; padding-top: 26px; vertical-align: bottom; }
    .sign .line { border-top: 1px solid #888; padding-top: 4px; color: #555; }
    .foot { margin-top: 22px; padding-top: 8px; border-top: 1px solid #d6dde5; font-size: 9px; color: #666; line-height: 1.6; text-align: center; }
</style>
</head>
<body>
    @php
        $paidAt = $transaction->paidAt() ?? $transaction->created_at;
        $client = $transaction->clientProfile?->user;
        $clientNo = rescue(fn () => $client?->clientLabel(), null, false);
        $cur = $transaction->currency;
        $money = fn ($v) => number_format((float) $v, 0);
        $list = $transaction->hasDiscount() ? (float) $transaction->original_amount : (float) $transaction->amount;
        $paid = (float) $transaction->amount;

        // Logo embedded as data, so it works on any server folder layout (cPanel too).
        $logoPath = public_path('images/mindwell-logo-horizontal.png');
        $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;

        $method = $transaction->isVoucherFree() ? 'Voucher (no payment taken)'
            : ($transaction->isCash() ? 'Cash' : ($transaction->method_label ?? 'Mobile Money'));
        // Helvetica can only draw Latin-1: turn "MTN Mobile Money · 0772 ••• 456" into
        // "MTN Mobile Money, 0772 xxx 456" (and the same for any other text we print).
        $latin = fn ($t) => strtr((string) $t, [' · ' => ', ', '·' => ',', '•' => 'x', '—' => '-', '–' => '-', '−' => '-', '’' => "'", '‘' => "'", '“' => '"', '”' => '"', '…' => '...']);
        $method = $latin($method);
    @endphp

    {{-- Header: logo left, practice details right --}}
    <table class="head">
        <tr>
            <td style="width:52%;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height:58px;" alt="Mindwell">
                @else
                    <div style="font-size:22px;font-weight:bold;" class="navy">Mindwell</div>
                @endif
            </td>
            <td class="practice">
                <div class="name">{{ $business['business_name'] ?? 'Mindwell' }}</div>
                @if (! empty($business['address'])){{ $business['address'] }}<br>@endif
                @if (! empty($business['phone']))Tel: {{ $business['phone'] }}<br>@endif
                @if (! empty($business['email']))Email: {{ $business['email'] }}<br>@endif
                @if (! empty($business['tin']))TIN: {{ $business['tin'] }}@endif
            </td>
        </tr>
    </table>
    <div class="rule"></div>
    <div class="rule-thin"></div>

    <div class="title">OFFICIAL RECEIPT</div>

    <table class="meta">
        <tr>
            <td class="k">Receipt No.</td><td class="v">{{ $transaction->receipt_number }}</td>
            <td class="k" style="text-align:right;padding-right:8px;">Date</td><td class="v" style="text-align:right;width:1%;white-space:nowrap;">{{ $paidAt->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="k">Transaction ref.</td><td class="v">{{ $transaction->tx_ref }}</td>
            <td class="k" style="text-align:right;padding-right:8px;">Time</td><td class="v" style="text-align:right;white-space:nowrap;">{{ $paidAt->format('h:i A') }}</td>
        </tr>
    </table>

    {{-- Client block --}}
    <table class="box">
        <tr>
            <td class="k">Received from</td>
            <td style="font-weight:bold;">{{ $latin($client?->name ?? 'Client') }}</td>
            <td class="k">Client No.</td>
            <td>{{ $clientNo ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="k">Paid by</td>
            <td>{{ $method }}</td>
            <td class="k">Email</td>
            <td>{{ $client?->email ?? 'N/A' }}</td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <tr>
            <th style="width:6%;" class="c">No.</th>
            <th>Description</th>
            <th style="width:8%;" class="c">Qty</th>
            <th style="width:18%;text-align:right;">Unit price ({{ $cur }})</th>
            <th style="width:18%;text-align:right;">Amount ({{ $cur }})</th>
        </tr>
        <tr>
            <td class="c">1</td>
            <td>{{ $latin($transaction->receiptDescription()) }}</td>
            <td class="c">1</td>
            <td class="num">{{ $money($list) }}</td>
            <td class="num">{{ $money($list) }}</td>
        </tr>
    </table>

    <table class="sum" style="margin-top:-1px;">
        <tr>
            <td class="label" style="width:82%;">Subtotal</td>
            <td class="num">{{ $money($list) }}</td>
        </tr>
        @if ($transaction->hasDiscount())
            <tr>
                <td class="label">Discount (voucher {{ $transaction->voucher?->code }})</td>
                <td class="num">-{{ $money($transaction->discount_amount) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td class="label" style="color:#034C8F;">TOTAL PAID ({{ $cur }})</td>
            <td class="num">{{ $money($paid) }}</td>
        </tr>
    </table>

    <div class="words">Amount in words: <b>{{ \App\Support\AmountInWords::forReceipt($paid, $cur) }}</b></div>

    <div class="status"><span class="stamp">PAID</span></div>

    @if ($transaction->isRefunded())
        <div class="note">
            <b>Refunded:</b> {{ $cur }} {{ $money($transaction->refund_amount) }} on {{ $transaction->refunded_at->format('d/m/Y') }}.
            Net amount paid: {{ $cur }} {{ $money($transaction->netAmount()) }}.
        </div>
    @endif

    @if ($transaction->isTest())
        <div class="note"><b>Test payment.</b> Made on the payment test system. No real money was taken.</div>
    @endif

    <table class="sign">
        <tr>
            <td style="width:45%;"><div class="line">Received by: Mindwell (system receipt)</div></td>
            <td style="width:10%;"></td>
            <td style="width:45%;"><div class="line">This receipt was produced by computer and needs no signature.</div></td>
        </tr>
    </table>

    <div class="foot">
        Thank you for choosing {{ $business['business_name'] ?? 'Mindwell' }}.
        Please keep this receipt. You can use it to claim the cost from your employer or insurer.<br>
        For questions about this payment, reply to your receipt email{{ ! empty($business['email']) ? ' or write to '.$business['email'] : '' }}.
        Printed {{ now()->format('d/m/Y h:i A') }}.
    </div>
</body>
</html>
