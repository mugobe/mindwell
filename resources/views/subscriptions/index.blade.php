@extends('layouts.app')
@section('title', 'My Plan')
@section('content')

<div class="dash-head" style="display:flex;align-items:center;gap:12px;">
    @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-teal-tint, #DCEDE8)', 'icon' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>'])
    <h1>My plan</h1>
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
    <div class="card" style="margin-bottom:20px;{{ $phase === 'grace' ? 'border-left:4px solid var(--coral-500, #E2725B);' : '' }}">
        <div class="section-title">Current plan</div>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-size:17px;font-weight:800;color:var(--ink-900);">{{ $currentPlan->name }}</div>
                <div style="font-size:12.5px;color:var(--ink-500);margin-top:2px;">
                    @if ($phase === 'renewed')
                        Renewed — your next period starts {{ $renewsOn->format('D j M') }} with {{ $planIncluded }} credit{{ $planIncluded === 1 ? '' : 's' }}.
                    @elseif ($phase === 'grace')
                        Was due for renewal on {{ $renewsOn->format('D j M') }}. You can keep using it until {{ $activeSubscription->lastUsableDay()->format('D j M') }}.
                    @elseif ($phase === 'ending_soon')
                        Renews on {{ $renewsOn->format('D j M') }} — {{ $daysToRenewal <= 0 ? 'today' : ($daysToRenewal === 1 ? 'tomorrow' : 'in '.$daysToRenewal.' days') }}
                    @else
                        This period: {{ $activeSubscription->current_period_start->format('j M') }} – renews {{ $renewsOn->format('D j M') }}
                    @endif
                </div>
            </div>
            <span class="pill {{ $phase === 'grace' ? 'warn' : 'success' }}">{{ $totalCreditsRemaining }} credit{{ $totalCreditsRemaining === 1 ? '' : 's' }} left</span>
        </div>

        @if ($phase === 'renewed')
            <div style="display:flex;align-items:center;gap:8px;margin-top:14px;font-size:12.5px;color:var(--ink-700);">
                <span class="pill teal">Renewed</span>
                Your current credits stay usable until then.
            </div>
        @elseif ($activeSubscription->canRenew())
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:16px;padding:12px 14px;border-radius:12px;background:{{ $urgent ? 'var(--mw-coral-tint, #FCE1D3)' : 'var(--mw-teal-tint, #DCEDE8)' }};">
                <div style="font-size:12.5px;color:var(--ink-700);max-width:420px;">
                    @if ($phase === 'grace')
                        Renew now to start a fresh period today with {{ $planIncluded }} credit{{ $planIncluded === 1 ? '' : 's' }}.
                    @else
                        Renew for {{ config('flutterwave.currency') }} {{ number_format($currentPlan->price, 0) }}. Your current credits stay until {{ $renewsOn->format('D j M') }}, then the new period starts with {{ $planIncluded }} credit{{ $planIncluded === 1 ? '' : 's' }}.
                    @endif
                </div>
                <a href="{{ route('subscription.renew') }}" class="btn {{ $urgent ? 'coral' : 'ghost' }} sm" style="border-radius:999px;padding-left:20px;padding-right:20px;">
                    {{ $urgent ? 'Renew now' : 'Renew early' }}
                </a>
            </div>
        @endif

        <p style="font-size:11.5px;color:var(--ink-300);margin:12px 0 0;">
            Each period starts with {{ $planIncluded }} credit{{ $planIncluded === 1 ? '' : 's' }}. Unused credits don't carry over to the next period.
        </p>
    </div>
@elseif ($endedSubscription)
    <div class="card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <div class="section-title" style="margin-bottom:4px;">Your plan has ended</div>
            <div style="font-size:12.5px;color:var(--ink-500);">
                Your {{ $endedSubscription->subscriptionPlan->name }} plan ended on {{ $endedSubscription->updated_at->format('D j M') }}. You can pick it up again any time.
            </div>
        </div>
        <a href="{{ route('subscription.checkout', $endedSubscription->subscriptionPlan) }}" class="btn coral sm" style="border-radius:999px;padding-left:20px;padding-right:20px;">
            Restart {{ $endedSubscription->subscriptionPlan->name }}
        </a>
    </div>
@endif

<div class="card">
    <div class="section-title">{{ $activeSubscription ? 'Switch plans' : 'Choose a plan' }}</div>

    @if ($activeSubscription)
        <p style="font-size:12.5px;color:var(--ink-500);margin-bottom:16px;">
            Switching replaces your current plan right away — it doesn't stack. Any unused credits (and a
            renewal you've already paid for) carry over as a bonus on your new plan; you'll see exactly how
            many before you confirm anything.
        </p>
    @else
        <p style="font-size:12.5px;color:var(--ink-500);margin-bottom:16px;">
            Subscribe to get session credits included in your plan, instead of paying per session each time.
        </p>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;">
        @forelse ($plans as $plan)
            @php
                $isCurrent = $activeSubscription && $activeSubscription->subscription_plan_id === $plan->id;
                // Rotate through a few of the pastel tints across plan cards so the grid
                // reads as colorful-but-on-brand rather than a wall of identical white cards.
                $mwPlanTint = [ 'var(--mw-coral-tint, #FCE1D3)', 'var(--mw-sage, #DEE8CE)', 'var(--mw-gold, #F6E1A8)' ][$loop->index % 3];
            @endphp
            <div class="card" style="border-top:4px solid {{ $mwPlanTint }};{{ $isCurrent ? ' border-color:var(--teal-500,#2f6f66);border-top-color:'.$mwPlanTint.';' : '' }}">
                <div style="font-weight:800;color:var(--ink-900);font-size:15px;">{{ $plan->name }}</div>
                <div style="font-size:22px;font-weight:800;color:var(--ink-900);margin:6px 0;">
                    {{ config('flutterwave.currency') }} {{ number_format($plan->price, 0) }}
                    <span style="font-size:11.5px;font-weight:600;color:var(--ink-300);">/ {{ $plan->billing_interval }}</span>
                </div>
                <div style="font-size:12.5px;color:var(--ink-500);margin-bottom:10px;">
                    {{ $plan->sessions_included }} session{{ $plan->sessions_included === 1 ? '' : 's' }} included
                </div>
                @if ($plan->features)
                    <ul style="font-size:11.5px;color:var(--ink-500);padding-left:16px;margin-bottom:14px;">
                        @foreach ($plan->features as $feature)
                            <li>{{ $feature }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($isCurrent)
                    <span class="pill teal" style="display:inline-block;">Current plan</span>
                @else
                    <a href="{{ route('subscription.checkout', $plan) }}" class="btn {{ $activeSubscription ? 'ghost' : 'coral' }} sm block" style="border-radius:999px;">
                        {{ $activeSubscription ? 'Switch to this plan' : 'Subscribe' }}
                    </a>
                @endif
            </div>
        @empty
            <div style="text-align:center;padding:24px;grid-column:1/-1;">
                @include('partials.mw-icon-badge', ['tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>'])
                <p style="color:var(--ink-500);font-size:13px;">No plans are available yet — check back soon.</p>
            </div>
        @endforelse
    </div>
</div>

@endsection
