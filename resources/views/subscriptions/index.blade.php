@extends('layouts.app')
@section('title', 'My Plan')
@section('content')

<style>
    .mwpl-hero { position:relative; overflow:hidden; display:flex; align-items:center; gap:26px; padding:26px 28px; border-radius:24px; color:#fff; margin-bottom:16px;
        background: radial-gradient(circle at 95% 0%, rgba(0,184,136,.45), transparent 42%), radial-gradient(circle at 75% 120%, rgba(248,128,0,.30), transparent 45%), linear-gradient(135deg, #022F5C, #034C8F 60%, #0B5FB0); }
    .mwpl-hero .eyebrow { font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; opacity:.75; }
    .mwpl-hero .name { font-size:26px; font-weight:800; letter-spacing:-.02em; margin:4px 0 6px; }
    .mwpl-hero .info { font-size:14px; opacity:.88; line-height:1.5; max-width:460px; }
    .mwpl-ring { position:relative; width:118px; height:118px; flex-shrink:0; }
    .mwpl-ring svg { width:118px; height:118px; transform:rotate(-90deg); }
    .mwpl-ring circle.track { stroke:rgba(255,255,255,.18); }
    .mwpl-ring circle.fill { stroke:#fff; stroke-linecap:round; transition:stroke-dashoffset .9s cubic-bezier(.2,.8,.2,1); animation:mwplRing 1s cubic-bezier(.2,.8,.2,1) both; }
    @keyframes mwplRing { from { stroke-dashoffset: 301.6; } }
    .mwpl-ring .n { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; line-height:1; }
    .mwpl-ring .n b { font-size:34px; font-weight:800; }
    .mwpl-ring .n span { font-size:11.5px; font-weight:700; opacity:.8; margin-top:4px; }
    .mwpl-plans { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:16px; }
    .mwpl-plan { display:flex; flex-direction:column; padding:22px; position:relative; }
    .mwpl-plan.is-current { box-shadow:0 0 0 2px var(--teal-700), 0 10px 26px rgba(3,40,80,.10); }
    .mwpl-plan .ic { width:42px; height:42px; border-radius:13px; display:flex; align-items:center; justify-content:center; margin-bottom:14px; }
    .mwpl-plan .ic svg { width:21px; height:21px; }
    .mwpl-plan .price { display:flex; align-items:baseline; flex-wrap:wrap; gap:4px 6px; font-size:24px; font-weight:800; color:var(--ink-900); letter-spacing:-.02em; margin:6px 0 2px; }
    .mwpl-plan .price small { font-size:12.5px; font-weight:600; color:var(--ink-500); letter-spacing:0; white-space:nowrap; }
    .mwpl-plan ul { list-style:none; margin:14px 0 18px; padding:0; display:flex; flex-direction:column; gap:8px; flex:1; }
    .mwpl-plan li { display:flex; gap:8px; font-size:13px; color:var(--ink-700); line-height:1.4; }
    .mwpl-plan li svg { width:16px; height:16px; flex-shrink:0; color:var(--mw-green, #00A87C); margin-top:1px; }
    .mwpl-badge { position:absolute; top:16px; right:16px; font-size:11px; font-weight:800; padding:4px 10px; border-radius:999px; background:var(--teal-700); color:#fff; }
    @media (max-width: 640px) { .mwpl-hero { flex-direction:column; align-items:flex-start; padding:22px; } }
    @media (prefers-reduced-motion: reduce) { .mwpl-ring circle.fill { animation:none; } }
</style>

<div class="dash-head">
    <div style="display:flex;align-items:center;gap:12px;margin-right:auto;">
        @include('partials.mw-icon-badge', ['size' => 44, 'margin' => '0', 'tint' => 'var(--mw-navy-tint, #E6EFF9)', 'icon' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>'])
        <div>
            <h1>My plan</h1>
            <div class="mwc-sub" style="margin-top:2px;">Session credits, renewals and plan options.</div>
        </div>
    </div>
    <a href="{{ route('payments.index') }}" class="btn ghost sm" style="border-radius:999px;">Payments and receipts</a>
</div>

@if ($activeSubscription)
    @php
        // Renewal-aware current plan card (2026-10-01) — see ClientSubscription::phase().
        $currentPlan = $activeSubscription->subscriptionPlan;
        $renewsOn = $activeSubscription->current_period_end;
        $daysToRenewal = (int) today()->diffInDays($renewsOn, false);
        $planIncluded = (int) ($currentPlan?->sessions_included ?? 0);
        $urgent = in_array($phase, ['ending_soon', 'grace'], true);
    @endphp
    @php
        $mwCircle = 301.6; // 2 x pi x 48
        $mwShare = $planIncluded > 0 ? min(1, $totalCreditsRemaining / $planIncluded) : ($totalCreditsRemaining > 0 ? 1 : 0);
    @endphp
    <div class="mwpl-hero">
        <div class="mwpl-ring" aria-label="{{ $totalCreditsRemaining }} session credits left">
            <svg viewBox="0 0 110 110" aria-hidden="true">
                <circle class="track" cx="55" cy="55" r="48" fill="none" stroke-width="10"/>
                <circle class="fill" cx="55" cy="55" r="48" fill="none" stroke-width="10" stroke-dasharray="{{ $mwCircle }}" stroke-dashoffset="{{ round($mwCircle * (1 - $mwShare), 1) }}"/>
            </svg>
            <div class="n"><b>{{ $totalCreditsRemaining }}</b><span>credit{{ $totalCreditsRemaining === 1 ? '' : 's' }} left</span></div>
        </div>
        <div style="flex:1;min-width:0;">
            <div class="eyebrow">{{ $phase === 'grace' ? 'Due for renewal' : 'Current plan' }}</div>
            <div class="name">{{ $currentPlan->name }}</div>
            <div class="info">
                @if ($phase === 'renewed')
                    Renewed. Your next period starts {{ $renewsOn->format('D j M') }} with {{ $planIncluded }} credit{{ $planIncluded === 1 ? '' : 's' }}. Your current credits stay usable until then.
                @elseif ($phase === 'grace')
                    Was due for renewal on {{ $renewsOn->format('D j M') }}. You can keep using it until {{ $activeSubscription->lastUsableDay()->format('D j M') }}.
                @elseif ($phase === 'ending_soon')
                    Renews on {{ $renewsOn->format('D j M') }}, {{ $daysToRenewal <= 0 ? 'today' : ($daysToRenewal === 1 ? 'tomorrow' : 'in '.$daysToRenewal.' days') }}.
                @else
                    This period runs from {{ $activeSubscription->current_period_start->format('j M') }} and renews on {{ $renewsOn->format('D j M') }}.
                @endif
                Each period starts with {{ $planIncluded }} credit{{ $planIncluded === 1 ? '' : 's' }}. Unused credits do not carry over.
            </div>
        </div>
    </div>

        @if ($phase !== 'renewed' && $activeSubscription->canRenew())
            <div class="mw-note {{ $urgent ? 'warn' : 'event' }}" style="margin:0 0 26px;">
                <div class="mw-note-body">
                    <div class="mw-note-title">{{ $phase === 'grace' ? 'Your plan is due for renewal' : 'Renew your plan' }}</div>
                    <div class="mw-note-text">
                    @if ($phase === 'grace')
                        Renew now to start a fresh period today with {{ $planIncluded }} credit{{ $planIncluded === 1 ? '' : 's' }}.
                    @else
                        Renew for {{ config('flutterwave.currency') }} {{ number_format($currentPlan->price, 0) }}. Your current credits stay until {{ $renewsOn->format('D j M') }}, then the new period starts with {{ $planIncluded }} credit{{ $planIncluded === 1 ? '' : 's' }}.
                    @endif
                    </div>
                </div>
                <div class="mw-note-act">
                    <a href="{{ route('subscription.renew') }}" class="mw-pill-btn {{ $urgent ? 'filled' : '' }}">{{ $urgent ? 'Renew now' : 'Renew early' }}</a>
                </div>
            </div>
        @endif
    <div style="height:10px;"></div>
@else
    {{-- No active plan (2026-11-01): same navy card as an active plan, so the page keeps its shape. --}}
    <div class="mwpl-hero">
        <div class="mwpl-ring" aria-hidden="true">
            <svg viewBox="0 0 110 110"><circle class="track" cx="55" cy="55" r="48" fill="none" stroke-width="10"/></svg>
            <div class="n"><b>0</b><span>credits</span></div>
        </div>
        <div style="flex:1;min-width:0;">
            <div class="eyebrow">{{ $endedSubscription ? 'Plan ended' : 'No plan yet' }}</div>
            <div class="name">{{ $endedSubscription ? $endedSubscription->subscriptionPlan->name : 'Paying per session' }}</div>
            <div class="info">
                @if ($endedSubscription)
                    Your plan ended on {{ $endedSubscription->updated_at->format('D j M') }}. You can pick it up again any time, or choose another plan below.
                @else
                    You pay for each session when you book it. A plan gives you session credits for each period instead.
                @endif
            </div>
            @if ($endedSubscription)
                <a href="{{ route('subscription.checkout', $endedSubscription->subscriptionPlan) }}" class="btn coral sm" style="border-radius:999px;padding:10px 20px;margin-top:14px;display:inline-block;">Restart {{ $endedSubscription->subscriptionPlan->name }}</a>
            @endif
        </div>
    </div>
    <div style="height:10px;"></div>
@endif

<h2 class="mwc-h2">{{ $activeSubscription ? 'Switch plans' : 'Choose a plan' }}</h2>
<p style="font-size:13.5px;color:var(--ink-500);margin:-4px 0 16px;max-width:680px;">
    @if ($activeSubscription)
        Switching replaces your current plan straight away. Plans do not add together. Unused credits, and any renewal you have already paid for,
        carry over as a bonus on your new plan. You will see exactly how many before you confirm.
    @else
        A plan gives you session credits for each period, instead of paying for every session separately.
    @endif
</p>

<div class="mwpl-plans mwc-stagger">
    @forelse ($plans as $plan)
        @php
            $isCurrent = $activeSubscription && $activeSubscription->subscription_plan_id === $plan->id;
            [$mwBg, $mwFg] = [['#FFEBD6', '#C25E00'], ['#DDF5EC', '#00A87C'], ['#E6EFF9', '#034C8F']][$loop->index % 3];
        @endphp
        <div class="card mwpl-plan mwc-lift {{ $isCurrent ? 'is-current' : '' }}">
            @if ($isCurrent)<span class="mwpl-badge">Your plan</span>@endif
            <span class="ic" style="background:{{ $mwBg }};color:{{ $mwFg }};"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/></svg></span>
            <div style="font-weight:800;color:var(--ink-900);font-size:16px;">{{ $plan->name }}</div>
            <div class="price">{{ config('flutterwave.currency') }} {{ number_format($plan->price, 0) }} <small>{{ ['weekly' => 'per week', 'monthly' => 'per month', 'yearly' => 'per year', 'annually' => 'per year', 'quarterly' => 'per quarter'][strtolower((string) $plan->billing_interval)] ?? '/ '.$plan->billing_interval }}</small></div>
            <ul>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>{{ $plan->sessions_included }} session{{ $plan->sessions_included === 1 ? '' : 's' }} included</li>
                {{-- Skip a feature that just repeats the sessions line above. --}}
                @foreach (collect($plan->features ?? [])->reject(fn ($f) => preg_match('/^\s*\d+\s+sessions?\s+included\s*$/i', (string) $f)) as $feature)
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>{{ $feature }}</li>
                @endforeach
            </ul>
            @if ($isCurrent)
                <span class="btn ghost sm block" style="border-radius:999px;pointer-events:none;opacity:.6;">Current plan</span>
            @else
                <a href="{{ route('subscription.checkout', $plan) }}" class="btn {{ $activeSubscription ? 'ghost' : 'coral' }} sm block" style="border-radius:999px;padding:10px;">
                    {{ $activeSubscription ? 'Switch to this plan' : 'Choose this plan' }}
                </a>
            @endif
        </div>
    @empty
        <div class="card" style="text-align:center;padding:30px;grid-column:1/-1;">
            @include('partials.mw-icon-badge', ['tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>'])
            <p style="color:var(--ink-500);font-size:13.5px;margin:0;">No plans are available yet. Please check back soon.</p>
        </div>
    @endforelse
</div>

@endsection
