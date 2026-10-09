@extends('layouts.app')
@section('title', 'Subscriptions')
@section('content')
{{-- Client subscriptions (2026-10-08 redesign): page head, "Grant a plan" panel that folds away,
     phase tabs with counts, search, credits meter, cash renewal under ⋯. --}}
@php
    $CS = \App\Models\ClientSubscription::class;
    $cur = config('flutterwave.currency', 'UGX');
    $phaseInfo = [
        $CS::PHASE_ACTIVE => ['ok', 'Active', 'active'],
        $CS::PHASE_ENDING_SOON => ['wait', 'Renews soon', 'renewing'],
        $CS::PHASE_RENEWED => ['ok', 'Renewed ahead', 'active'],
        $CS::PHASE_ROLL_DUE => ['ok', 'Renewed ahead', 'active'],
        $CS::PHASE_GRACE => ['wait', 'Grace period', 'renewing'],
        $CS::PHASE_EXPIRE_DUE => ['bad', 'Lapsing', 'renewing'],
        $CS::PHASE_ENDED => ['', 'Ended', 'ended'],
    ];
    $tabs = ['active' => 'Active', 'renewing' => 'Needs renewal', 'ended' => 'Ended'];
    $rows = $subscriptions->map(function ($sub) use ($phaseInfo) {
        $phase = $sub->phase();
        [$dot, $label, $group] = $phaseInfo[$phase] ?? ['bad', ucfirst($sub->status), 'ended'];

        return compact('sub', 'phase', 'dot', 'label', 'group');
    });
    $counts = $rows->countBy('group');
    $tab = array_key_exists((string) request('tab'), $tabs) ? (string) request('tab') : null;
    $q = trim((string) request('q', ''));
    $shown = $rows
        ->when($tab, fn ($c) => $c->where('group', $tab))
        ->when($q !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower(($r['sub']->clientProfile?->user?->name ?? '').' '.($r['sub']->clientProfile?->user?->email ?? '').' '.($r['sub']->subscriptionPlan?->name ?? '')), mb_strtolower($q))))
        ->values();
    $grantOpen = $errors->has('user_id') || $errors->has('subscription_plan_id') || $errors->has('cash_amount') || old('payment_type');
@endphp

<div class="mw-page-head">
    <div>
        <h1>Subscriptions</h1>
        <div class="sub">Clients on a monthly plan. They subscribe and renew themselves from My plan; use Grant for a plan paid another way.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.plans.index') }}" class="btn ghost sm">Plans &amp; pricing</a>
        <button type="button" class="btn primary sm" onclick="var d=document.getElementById('mw-grant'); d.open=true; d.scrollIntoView({behavior:'smooth',block:'start'}); document.getElementById('user_id').focus();">Grant a plan</button>
    </div>
</div>

<details id="mw-grant" class="card" style="margin-bottom:18px;padding:0;" {{ $grantOpen ? 'open' : '' }}>
    <summary class="mw-card-head" style="cursor:pointer;list-style:none;">
        <h2>Grant a plan</h2>
        <span style="font-size:12px;color:var(--ink-500);">Complimentary, or paid in cash (a receipt is emailed)</span>
    </summary>
    <form method="POST" action="{{ route('admin.client-subscriptions.store') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;padding:4px 18px 18px;">
        @csrf
        <div>
            <label class="mw-label" for="user_id">Client</label>
            <select class="mw-field" id="user_id" name="user_id" required style="width:240px;">
                <option value="">Choose…</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" {{ old('user_id') == $client->id ? 'selected' : '' }}>{{ $client->name }} ({{ $client->email }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mw-label" for="subscription_plan_id">Plan</label>
            <select class="mw-field" id="subscription_plan_id" name="subscription_plan_id" required style="width:220px;">
                <option value="">Choose…</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" {{ old('subscription_plan_id') == $plan->id ? 'selected' : '' }}>{{ $plan->name }} ({{ $plan->sessions_included }} sessions / {{ $plan->billing_interval }})</option>
                @endforeach
            </select>
        </div>
        {{-- 2026-10-06 (roadmap 23): free, or paid in cash (creates a payment + receipt). --}}
        <div>
            <label class="mw-label" for="payment_type">Payment</label>
            <select class="mw-field" id="payment_type" name="payment_type" style="width:170px;" onchange="document.getElementById('mw-cash-wrap').style.display = this.value === 'cash' ? 'block' : 'none'">
                <option value="complimentary" {{ old('payment_type') !== 'cash' ? 'selected' : '' }}>Complimentary</option>
                <option value="cash" {{ old('payment_type') === 'cash' ? 'selected' : '' }}>Paid in cash</option>
            </select>
        </div>
        <div id="mw-cash-wrap" style="display:{{ old('payment_type') === 'cash' ? 'block' : 'none' }};">
            <label class="mw-label" for="cash_amount">Amount ({{ $cur }})</label>
            <input class="mw-field" type="number" min="0" step="1" id="cash_amount" name="cash_amount" value="{{ old('cash_amount') }}" style="width:140px;">
        </div>
        <button type="submit" class="btn primary sm" style="height:41px;">Grant</button>
    </form>
    <div style="padding:0 18px;">
        @error('cash_amount')<div class="mw-error" style="margin-bottom:10px;">{{ $message }}</div>@enderror
        @error('user_id')<div class="mw-error" style="margin-bottom:10px;">{{ $message }}</div>@enderror
        @error('subscription_plan_id')<div class="mw-error" style="margin-bottom:10px;">{{ $message }}</div>@enderror
    </div>
</details>

<nav class="mw-tabs" aria-label="Filter subscriptions">
    <a href="{{ route('admin.client-subscriptions.index', array_filter(['q' => $q ?: null])) }}" class="{{ $tab ? '' : 'on' }}">All <span class="n">{{ $rows->count() }}</span></a>
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.client-subscriptions.index', array_filter(['tab' => $key, 'q' => $q ?: null])) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }} <span class="n">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>

<form method="GET" action="{{ route('admin.client-subscriptions.index') }}" class="mw-toolbar">
    @if ($tab)<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <label class="mw-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Search by client or plan" aria-label="Search subscriptions">
    </label>
    @if ($q !== '')
        <a href="{{ route('admin.client-subscriptions.index', array_filter(['tab' => $tab])) }}" class="btn ghost sm">Clear</a>
    @endif
    <span style="margin-left:auto;font-size:12.5px;color:var(--ink-500);">{{ $shown->count() }} {{ $shown->count() === 1 ? 'subscription' : 'subscriptions' }}</span>
</form>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Plan</th>
                <th>Sessions left</th>
                <th>Period</th>
                <th>Status</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shown as $row)
                @php
                    $sub = $row['sub'];
                    $incl = (int) ($sub->subscriptionPlan?->sessions_included ?? 0);
                    $left = (int) $sub->sessions_remaining;
                    $pct = $incl ? max(0, min(100, round($left / $incl * 100))) : 0;
                    $canCash = $sub->canRenew() && in_array($row['phase'], [$CS::PHASE_ACTIVE, $CS::PHASE_ENDING_SOON, $CS::PHASE_GRACE], true);
                @endphp
                <tr>
                    <td style="padding-left:18px;">@include('partials.person', ['name' => $sub->clientProfile?->user?->name, 'sub' => $sub->clientProfile?->user?->email, 'href' => $sub->clientProfile ? route('admin.clients.show', $sub->clientProfile) : null])</td>
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $sub->subscriptionPlan?->name ?? '—' }}</div>
                        @if ($sub->subscriptionPlan)<div style="font-size:11.5px;color:var(--ink-500);">{{ $cur }} {{ number_format($sub->subscriptionPlan->price, 0) }} / {{ $sub->subscriptionPlan->billing_interval }}</div>@endif
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <strong style="font-variant-numeric:tabular-nums;">{{ $left }}</strong>@if ($incl)<span style="font-size:11px;color:var(--ink-300);">of {{ $incl }}</span>@endif
                            @if ($incl)<span class="mw-meter"><span style="width:{{ $pct }}%;background:{{ $left === 0 ? '#C0392B' : 'var(--teal-600, #2f6f66)' }};"></span></span>@endif
                        </div>
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $sub->current_period_start->format('j M') }} – {{ $sub->current_period_end->format('j M Y') }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">{{ $row['group'] === 'ended' ? 'Ended' : 'Renews '.$sub->current_period_end->format('j M') }}</div>
                    </td>
                    <td style="white-space:nowrap;"><span class="mw-dot {{ $row['dot'] }}">{{ $row['label'] }}</span></td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        @if ($canCash)
                            {{-- Cash renewal (2026-10-06, roadmap 23), now under ⋯. --}}
                            <details class="mw-menu">
                                <summary aria-label="More actions">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                </summary>
                                <div class="pop" style="min-width:250px;padding:12px;">
                                    <div style="font-size:12.5px;font-weight:800;color:var(--ink-900);margin-bottom:6px;">Record cash renewal</div>
                                    <form method="POST" action="{{ route('admin.client-subscriptions.renew-cash', $sub) }}" style="display:flex;gap:6px;align-items:center;" onsubmit="return confirm('Record a cash renewal for this plan? A receipt will be emailed to the client.');">
                                        @csrf
                                        <input class="mw-field" type="number" min="0" step="1" name="cash_amount" value="{{ (int) $sub->subscriptionPlan?->price }}" style="width:120px;padding:6px 8px;" aria-label="Cash amount ({{ $cur }})">
                                        <button type="submit" class="btn primary sm">Save</button>
                                    </form>
                                    <div style="font-size:11px;color:var(--ink-500);margin-top:6px;">{{ $cur }} · a receipt is emailed to the client</div>
                                </div>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h12l-3-3M20 16H8l3 3"/></svg>
                            <div class="big">{{ $tab || $q !== '' ? 'No subscriptions match' : 'No subscriptions yet' }}</div>
                            {{ $tab || $q !== '' ? 'Try another tab or name.' : 'Clients appear here once they subscribe, or when you grant them a plan.' }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.mw-menu[open]').forEach(function (d) { if (! d.contains(e.target)) d.removeAttribute('open'); });
    });
</script>
@endsection
