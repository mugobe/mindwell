@extends('layouts.app')
@section('title', 'Payments')
@section('content')
{{-- Added 2026-10-02 — see Admin\PaymentController. Chart colours validated with the
     dataviz palette checker (teal #139080 / coral #d9693f pass CVD + contrast). --}}
<style>
    .mw-pay-tiles { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:18px; }
    .mw-pay-tile .k { font-size:11.5px; font-weight:700; color:var(--ink-500); text-transform:uppercase; letter-spacing:.03em; }
    .mw-pay-tile .v { font-size:22px; font-weight:800; color:var(--ink-900); margin-top:4px; font-variant-numeric:tabular-nums; }
    .mw-pay-tile .s { font-size:12px; color:var(--ink-500); margin-top:2px; }
    .mw-swatch { display:inline-block; width:10px; height:10px; border-radius:3px; vertical-align:middle; margin-right:5px; }
    .mw-bars { display:flex; align-items:flex-end; gap:14px; height:190px; padding:0 4px; border-bottom:1px solid rgba(0,0,0,.12); }
    .mw-bar { flex:1; display:flex; flex-direction:column; justify-content:flex-end; align-items:stretch; height:100%; position:relative; text-decoration:none; min-width:0; }
    .mw-bar .tot { font-size:11px; font-weight:700; color:var(--ink-700); text-align:center; margin-bottom:4px; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .mw-bar .seg { max-width:46px; width:100%; margin:0 auto; }
    .mw-bar .seg.plans { background:#d9693f; border-radius:4px 4px 0 0; }
    .mw-bar .seg.sessions { background:#139080; }
    .mw-bar .seg.plans + .seg.sessions { border-top:2px solid #fff; }
    .mw-bar .seg.top { border-radius:4px 4px 0 0; }
    .mw-bar .tip { display:none; position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%); background:#1d2b29; color:#fff; font-size:11.5px; line-height:1.5; padding:7px 10px; border-radius:8px; white-space:nowrap; z-index:5; pointer-events:none; }
    .mw-bar:hover .tip, .mw-bar:focus-visible .tip { display:block; }
    .mw-bar:hover .seg { filter:brightness(1.06); }
    .mw-bar.current .lbl { color:var(--ink-900); font-weight:800; }
    .mw-bar-labels { display:flex; gap:14px; padding:6px 4px 0; }
    .mw-bar-labels span { flex:1; text-align:center; font-size:11.5px; color:var(--ink-500); }
</style>

{{-- 2026-10-08 redesign: page kit header + toolbar (see layouts/app.blade.php). --}}
@php
    $mwQ = array_filter(['kind' => $filters['kind'] ?? null, 'status' => $filters['status'] ?? null]);
    $mwPrev = $month->copy()->subMonthNoOverflow()->format('Y-m');
    $mwNext = $month->copy()->addMonthNoOverflow()->format('Y-m');
@endphp
<div class="mw-page-head">
    <div>
        <h1>Payments</h1>
        <div class="sub">Money received through Mindwell — Mobile Money, cash and vouchers — for {{ $month->format('F Y') }}.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.payments.index', array_merge($mwQ, ['month' => $mwPrev])) }}" class="btn ghost sm" aria-label="Previous month">‹</a>
        <a href="{{ route('admin.payments.index', $mwQ) }}" class="btn ghost sm">This month</a>
        <a href="{{ route('admin.payments.index', array_merge($mwQ, ['month' => $mwNext])) }}" class="btn ghost sm" aria-label="Next month">›</a>
        {{-- CSV export (2026-10-06, roadmap 24) — same month and filters. --}}
        <a href="{{ route('admin.payments.export', array_merge($mwQ, ['month' => $month->format('Y-m')])) }}" class="btn primary sm">Download CSV</a>
    </div>
</div>

<form method="GET" action="{{ route('admin.payments.index') }}" class="mw-toolbar">
    <input class="mw-field" type="month" name="month" value="{{ $month->format('Y-m') }}" style="width:170px;height:38px;" aria-label="Month">
    <select class="mw-field" name="kind" style="width:170px;height:38px;" aria-label="Type">
        <option value="">All types</option>
        <option value="session" {{ ($filters['kind'] ?? '') === 'session' ? 'selected' : '' }}>Single sessions</option>
        <option value="plan" {{ ($filters['kind'] ?? '') === 'plan' ? 'selected' : '' }}>New plans</option>
        <option value="renewal" {{ ($filters['kind'] ?? '') === 'renewal' ? 'selected' : '' }}>Plan renewals</option>
    </select>
    <select class="mw-field" name="status" style="width:160px;height:38px;" aria-label="Status">
        <option value="">All statuses</option>
        @foreach (['successful' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $value => $label)
            <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn ghost sm" style="height:38px;">Apply</button>
    @if ($mwQ)
        <a href="{{ route('admin.payments.index', ['month' => $month->format('Y-m')]) }}" class="btn ghost sm" style="height:38px;display:inline-flex;align-items:center;">Clear filters</a>
    @endif
</form>

@if ($stuck > 0)
    <div class="mw-alert danger" style="margin-bottom:14px;">
        {{ $stuck }} payment{{ $stuck === 1 ? ' was' : 's were' }} started over 30 minutes ago and never confirmed. Usually the client
        didn't approve the prompt on their phone, but if a client says they were charged, check Flutterwave for that reference.
    </div>
@endif

<div class="mw-kpis">
    <div class="mw-kpi mw-pay-tile">
        <div class="k">Received in {{ $month->format('F') }}</div>
        <div class="v">{{ $currency }} {{ number_format($summary['total'], 0) }}</div>
        <div class="s">{{ $summary['count'] }} payment{{ $summary['count'] === 1 ? '' : 's' }}{{ ($summary['refunded'] ?? 0) > 0 ? ' · after '.$currency.' '.number_format($summary['refunded'], 0).' refunded' : '' }}</div>
    </div>
    <div class="mw-kpi mw-pay-tile">
        <div class="k"><span class="mw-swatch" style="background:#139080;"></span>Single sessions</div>
        <div class="v">{{ $currency }} {{ number_format($summary['session']['total'], 0) }}</div>
        <div class="s">{{ $summary['session']['count'] }} paid</div>
    </div>
    <div class="mw-kpi mw-pay-tile">
        <div class="k"><span class="mw-swatch" style="background:#d9693f;"></span>New plans</div>
        <div class="v">{{ $currency }} {{ number_format($summary['plan']['total'], 0) }}</div>
        <div class="s">{{ $summary['plan']['count'] }} bought</div>
    </div>
    <div class="mw-kpi mw-pay-tile">
        <div class="k"><span class="mw-swatch" style="background:#d9693f;"></span>Plan renewals</div>
        <div class="v">{{ $currency }} {{ number_format($summary['renewal']['total'], 0) }}</div>
        <div class="s">{{ $summary['renewal']['count'] }} renewed</div>
    </div>
</div>

@php
    $trendMax = max(1, collect($trend)->max(fn ($t) => $t['sessions'] + $t['plans']));
    $short = fn (float $v) => $v >= 1000000 ? rtrim(rtrim(number_format($v / 1000000, 1), '0'), '.').'M' : ($v >= 1000 ? number_format($v / 1000, 0).'k' : number_format($v, 0));
@endphp
<div class="card" style="margin-bottom:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:22px;">
        <div class="section-title" style="margin:0;">Last six months ({{ $currency }})</div>
        <div style="font-size:12px;color:var(--ink-500);">
            <span class="mw-swatch" style="background:#139080;"></span>Single sessions
            <span class="mw-swatch" style="background:#d9693f;margin-left:12px;"></span>Plans &amp; renewals
        </div>
    </div>
    <div class="mw-bars" role="img" aria-label="Payments received per month for the last six months, split into single sessions and plans">
        @foreach ($trend as $t)
            @php $tTotal = $t['sessions'] + $t['plans']; @endphp
            <a class="mw-bar {{ $t['key'] === $month->format('Y-m') ? 'current' : '' }}" href="{{ route('admin.payments.index', ['month' => $t['key']]) }}">
                <span class="tip">
                    <strong>{{ $t['label'] }}: {{ $currency }} {{ number_format($tTotal, 0) }}</strong><br>
                    Single sessions: {{ number_format($t['sessions'], 0) }}<br>
                    Plans &amp; renewals: {{ number_format($t['plans'], 0) }}
                </span>
                @if ($tTotal > 0)
                    <span class="tot">{{ $short($tTotal) }}</span>
                @endif
                @if ($t['plans'] > 0)
                    <span class="seg plans" style="height:{{ round($t['plans'] / $trendMax * 160, 1) }}px;"></span>
                @endif
                @if ($t['sessions'] > 0)
                    <span class="seg sessions {{ $t['plans'] > 0 ? '' : 'top' }}" style="height:{{ round($t['sessions'] / $trendMax * 160, 1) }}px;"></span>
                @endif
            </a>
        @endforeach
    </div>
    <div class="mw-bar-labels">
        @foreach ($trend as $t)
            <span style="{{ $t['key'] === $month->format('Y-m') ? 'color:var(--ink-900);font-weight:800;' : '' }}">{{ $t['label'] }}</span>
        @endforeach
    </div>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Date</th>
                <th>Client</th>
                <th>What for</th>
                <th>Amount</th>
                <th>Status</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                @php
                    [$pillClass, $pillLabel] = match (true) {
                        $payment->isRefunded() => ['warn', $payment->isFullyRefunded() ? 'Refunded' : 'Part refunded'],
                        default => match ($payment->status) {
                        'successful' => ['success', 'Paid'],
                        'pending' => ['warn', 'Pending'],
                        'failed' => ['danger', 'Failed'],
                        default => ['danger', ucfirst($payment->status)],
                        },
                    };
                @endphp
                <tr>
                    <td style="padding-left:16px;white-space:nowrap;font-size:12.5px;">{{ $payment->created_at->format('M j, g:i A') }}</td>
                    @php $mwName = $payment->clientProfile?->user?->name; @endphp
                    <td>
                        @include('partials.person', ['name' => $mwName, 'size' => 28, 'href' => $payment->clientProfile ? route('admin.clients.show', $payment->clientProfile) : null])
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--ink-900);">{{ $payment->receiptDescription() }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">
                            {{ $payment->kindLabel() }} · {{ $payment->method_label ?? 'Mobile Money' }} · {{ $payment->tx_ref }}{{ $payment->isTest() ? ' · test' : '' }}
                        </div>
                    </td>
                    <td style="white-space:nowrap;font-weight:700;">
                        {{ $payment->currency }} {{ number_format((float) $payment->amount, 0) }}
                        @if ($payment->isRefunded())
                            <div style="font-size:11.5px;font-weight:600;color:var(--danger, #c0392b);">−{{ number_format((float) $payment->refund_amount, 0) }} refunded</div>
                        @endif
                        @if ($payment->hasDiscount())
                            <div style="font-size:11.5px;font-weight:600;color:var(--ink-500);">{{ $payment->voucher?->code ?? 'Voucher' }} −{{ number_format((float) $payment->discount_amount, 0) }}</div>
                        @endif
                    </td>
                    <td style="white-space:nowrap;"><span class="pill {{ $pillClass }}">{{ $pillLabel }}</span></td>
                    <td style="padding-right:16px;text-align:right;white-space:nowrap;position:relative;">
                        @if ($payment->status === 'successful')
                            <a href="{{ route('admin.payments.receipt', $payment) }}" class="btn ghost sm">{{ $payment->receipt_number ?? 'Receipt' }}</a>
                            @if (! $payment->isRefunded() && (float) $payment->amount > 0)
                                {{-- Record a refund (2026-10-06, roadmap 22). Send the money back first. --}}
                                <details class="mw-menu" style="vertical-align:middle;margin-left:4px;">
                                    <summary aria-label="More actions"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg></summary>
                                    <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="pop" style="width:300px;padding:14px;display:flex;flex-direction:column;gap:8px;white-space:normal;" onsubmit="return confirm('Record this refund? Make sure you have already sent the money back to the client.');">
                                        @csrf
                                        <div style="font-size:13px;font-weight:800;color:var(--ink-900);">Record a refund</div>
                                        <div style="font-size:12px;color:var(--ink-700);">Send the money back first (Mobile Money / Flutterwave dashboard), then record it here. The client is emailed.</div>
                                        <label class="mw-label" style="margin:0;">Amount ({{ $payment->currency }})</label>
                                        <input class="mw-field" type="number" name="refund_amount" min="1" max="{{ (float) $payment->amount }}" step="1" value="{{ (int) $payment->amount }}" required>
                                        <label class="mw-label" style="margin:0;">Reason</label>
                                        <input class="mw-field" name="refund_reason" maxlength="500" required placeholder="e.g. Session cancelled by Mindwell">
                                        <label style="font-size:12px;color:var(--ink-700);display:flex;gap:6px;align-items:flex-start;">
                                            <input type="checkbox" name="cancel_linked" value="1" checked style="margin-top:2px;">
                                            {{ $payment->purpose === 'session' ? 'Also cancel the session (if it hasn\'t happened yet)' : ($payment->isRenewal() ? 'Also undo the pre-paid renewal (if not started yet)' : 'Also end the plan') }}
                                        </label>
                                        <button type="submit" class="btn primary sm">Record refund</button>
                                    </form>
                                </details>
                            @else
                                <div style="font-size:11px;color:var(--ink-500);margin-top:4px;white-space:normal;max-width:200px;">{{ $payment->refund_reason }}</div>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="mw-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/></svg><div class="big">No payments</div>Nothing in {{ $month->format('F Y') }}{{ ! empty($filters['kind']) || ! empty($filters['status']) ? ' matching these filters' : ' yet' }}.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $payments->links() }}</div>
<script>
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.mw-menu[open]').forEach(function (d) { if (! d.contains(e.target)) d.removeAttribute('open'); });
    });
</script>
@endsection
