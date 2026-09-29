@extends('layouts.app')
@section('title', 'My Plan')
@section('content')

<div class="dash-head" style="display:flex;align-items:center;gap:12px;">
    @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-teal-tint, #DCEDE8)', 'icon' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>'])
    <h1>My plan</h1>
</div>

@if ($activeSubscription)
    <div class="card" style="margin-bottom:20px;">
        <div class="section-title">Current plan</div>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-size:17px;font-weight:800;color:var(--ink-900);">{{ $activeSubscription->subscriptionPlan->name }}</div>
                <div style="font-size:12.5px;color:var(--ink-500);margin-top:2px;">
                    Renews {{ $activeSubscription->current_period_end?->format('M j, Y') ?? '—' }}
                </div>
            </div>
            <span class="pill success">{{ $totalCreditsRemaining }} credit{{ $totalCreditsRemaining === 1 ? '' : 's' }} left</span>
        </div>
    </div>
@endif

<div class="card">
    <div class="section-title">{{ $activeSubscription ? 'Switch plans' : 'Choose a plan' }}</div>

    @if ($activeSubscription)
        <p style="font-size:12.5px;color:var(--ink-500);margin-bottom:16px;">
            Switching replaces your current plan right away — it doesn't stack. Any unused credits carry
            over as a bonus on your new plan; you'll see exactly how many before you confirm anything.
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

<p style="font-size:10.5px;color:var(--ink-300);margin-top:14px;">
    Test placeholder: no real payment is charged yet for subscriptions. Real Flutterwave billing isn't wired
    up yet.
</p>
@endsection
