@extends('layouts.app')
@section('title', 'Vouchers')
@section('content')
{{-- Admin → Vouchers (2026-10-07, roadmap 25). See App\Http\Controllers\Admin\VoucherController.
     2026-10-08 redesign: page head, KPI tiles, Live / Not live tabs, code chips, usage meter, ⋯ menu. --}}
@php
    $mwCur = config('flutterwave.currency', 'UGX');
    $rows = $vouchers->map(function ($v) {
        [$pc, $pl] = $v->statusLabel($v->redemptions_count);

        return ['v' => $v, 'pill' => $pc, 'label' => $pl, 'live' => $pl === 'Live'];
    });
    $tab = in_array(request('tab'), ['live', 'off'], true) ? request('tab') : null;
    $shown = $rows->when($tab, fn ($c) => $c->filter(fn ($r) => $tab === 'live' ? $r['live'] : ! $r['live']))->values();
    $liveCount = $rows->where('live', true)->count();
    $dot = ['success' => 'ok', 'warn' => 'wait', '' => ''];
@endphp

<div class="mw-page-head">
    <div>
        <h1>Vouchers</h1>
        <div class="sub">Discount codes clients type at checkout. A use only counts once the payment goes through.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.vouchers.create') }}" class="btn primary sm">New voucher</a></div>
</div>

<div class="mw-kpis">
    <div class="mw-kpi">
        <div class="k">Live now</div>
        <div class="v">{{ $liveCount }}</div>
        <div class="d">of {{ $vouchers->count() }} {{ $vouchers->count() === 1 ? 'voucher' : 'vouchers' }}</div>
    </div>
    <div class="mw-kpi">
        <div class="k">Times used</div>
        <div class="v">{{ $vouchers->sum('redemptions_count') }}</div>
        <div class="d">Paid checkouts with a code</div>
    </div>
    <div class="mw-kpi">
        <div class="k">Discount given</div>
        <div class="v">{{ $mwCur }} {{ number_format((float) $vouchers->sum('redemptions_sum_discount_amount'), 0) }}</div>
        <div class="d">All time</div>
    </div>
</div>

<nav class="mw-tabs" aria-label="Filter vouchers">
    <a href="{{ route('admin.vouchers.index') }}" class="{{ $tab ? '' : 'on' }}">All <span class="n">{{ $rows->count() }}</span></a>
    <a href="{{ route('admin.vouchers.index', ['tab' => 'live']) }}" class="{{ $tab === 'live' ? 'on' : '' }}">Live <span class="n">{{ $liveCount }}</span></a>
    <a href="{{ route('admin.vouchers.index', ['tab' => 'off']) }}" class="{{ $tab === 'off' ? 'on' : '' }}">Not live <span class="n">{{ $rows->count() - $liveCount }}</span></a>
</nav>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Code</th>
                <th>Discount</th>
                <th>Works on</th>
                <th>Who</th>
                <th>Valid</th>
                <th>Used</th>
                <th>Status</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shown as $row)
                @php $v = $row['v']; @endphp
                <tr>
                    <td style="padding-left:18px;">
                        <span class="mw-code">{{ $v->code }}</span>
                        @if ($v->description)
                            <div style="font-size:11.5px;color:var(--ink-500);max-width:220px;margin-top:4px;">{{ $v->description }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--ink-900);">{{ $v->discountLabel() }}</div>
                        @if ($v->min_amount)
                            <div style="font-size:11.5px;color:var(--ink-300);">on {{ $mwCur }} {{ number_format((float) $v->min_amount, 0) }}+</div>
                        @endif
                    </td>
                    <td style="font-size:12.5px;">{{ $v->appliesLabel() }}</td>
                    <td style="font-size:12.5px;">
                        @if ($v->client)
                            @include('partials.person', ['name' => $v->client->name, 'sub' => 'Only this client', 'size' => 26])
                        @else
                            {{ $v->new_clients_only ? 'New clients' : 'Anyone' }}
                            <div style="font-size:11px;color:var(--ink-300);">{{ $v->max_uses_per_client ? $v->max_uses_per_client.'× per client' : 'no per-client limit' }}</div>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:12.5px;color:var(--ink-900);">{{ $v->starts_at ? $v->starts_at->format('j M Y') : 'From now' }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">{{ $v->ends_at ? 'until '.$v->ends_at->format('j M Y') : 'no end date' }}</div>
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <strong style="font-variant-numeric:tabular-nums;">{{ $v->redemptions_count }}</strong>
                            @if ($v->max_uses_total)
                                <span style="font-size:11px;color:var(--ink-300);">of {{ $v->max_uses_total }}</span>
                                <span class="mw-meter"><span style="width:{{ min(100, round($v->redemptions_count / max(1, $v->max_uses_total) * 100)) }}%;background:var(--mw-orange, #E8825E);"></span></span>
                            @endif
                        </div>
                        @if ($v->redemptions_sum_discount_amount > 0)
                            <div style="font-size:11px;color:var(--ink-300);">{{ $mwCur }} {{ number_format((float) $v->redemptions_sum_discount_amount, 0) }} given</div>
                        @endif
                    </td>
                    <td style="white-space:nowrap;"><span class="mw-dot {{ $dot[$row['pill']] ?? '' }}">{{ $row['label'] }}</span></td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        <a href="{{ route('admin.vouchers.edit', $v) }}" class="btn ghost sm">Edit</a>
                        <details class="mw-menu" style="vertical-align:middle;margin-left:4px;">
                            <summary aria-label="More actions for {{ $v->code }}">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                            </summary>
                            <div class="pop">
                                <button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText({{ json_encode($v->code) }}).then(() => { this.textContent = 'Copied'; })">Copy code</button>
                                <form method="POST" action="{{ route('admin.vouchers.toggle', $v) }}">
                                    @csrf
                                    <button type="submit" class="{{ $v->is_active ? 'danger' : '' }}">{{ $v->is_active ? 'Switch off' : 'Switch on' }}</button>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a2 2 0 002-2h14a2 2 0 002 2v2a2 2 0 000 4v2a2 2 0 00-2 2H5a2 2 0 00-2-2v-2a2 2 0 000-4z"/><path d="M10 6v12" stroke-dasharray="2 2"/></svg>
                            <div class="big">{{ $tab ? 'No vouchers here' : 'No vouchers yet' }}</div>
                            @unless ($tab)
                                Create one to offer a discount on sessions, plans or renewals.
                                <div style="margin-top:10px;"><a href="{{ route('admin.vouchers.create') }}" class="btn primary sm">New voucher</a></div>
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    A voucher that takes the price to zero skips Mobile Money and still issues a receipt. Used vouchers can't be deleted — switch them off.
</p>

<script>
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.mw-menu[open]').forEach(function (d) { if (! d.contains(e.target)) d.removeAttribute('open'); });
    });
</script>
@endsection
