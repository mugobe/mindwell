@extends('layouts.app')
@section('title', 'Plans & pricing')
@section('content')
{{-- Subscription plans (2026-10-08 redesign): plan cards like a pricing page, with how many
     clients are on each. See Admin\SubscriptionPlanController::index(). --}}
@php $cur = config('flutterwave.currency', 'UGX'); @endphp

<div class="mw-page-head">
    <div>
        <h1>Plans &amp; pricing</h1>
        <div class="sub">The monthly plans clients can buy from My plan. Changing a price affects new purchases and renewals, not what was already paid.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.client-subscriptions.index') }}" class="btn ghost sm">Subscriptions</a>
        <a href="{{ route('admin.plans.create') }}" class="btn primary sm">Add plan</a>
    </div>
</div>

@if ($plans->isEmpty())
    <div class="card mw-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/></svg>
        <div class="big">No plans yet</div>
        Add one to start selling subscriptions.
        <div style="margin-top:10px;"><a href="{{ route('admin.plans.create') }}" class="btn primary sm">Add plan</a></div>
    </div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px;">
        @foreach ($plans as $plan)
            @php $on = (int) ($activeCounts[$plan->id] ?? 0); @endphp
            <div class="card" style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <div style="font-size:15px;font-weight:800;color:var(--ink-900);">{{ $plan->name }}</div>
                    <span class="pill teal">{{ ucfirst($plan->billing_interval) }}</span>
                </div>
                <div>
                    <span style="font-size:12px;font-weight:700;color:var(--ink-500);">{{ $cur }}</span>
                    <span style="font-size:28px;font-weight:800;color:var(--ink-900);letter-spacing:-.01em;">{{ number_format($plan->price, 0) }}</span>
                    <span style="font-size:12px;color:var(--ink-500);">/ {{ $plan->billing_interval }}</span>
                </div>
                <div style="font-size:13px;color:var(--ink-700);"><strong>{{ $plan->sessions_included }}</strong> {{ $plan->sessions_included == 1 ? 'session' : 'sessions' }} each period
                    @if ($plan->sessions_included)
                        <span style="color:var(--ink-300);">· {{ $cur }} {{ number_format($plan->price / $plan->sessions_included, 0) }} a session</span>
                    @endif
                </div>
                @if ($plan->features)
                    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:5px;">
                        @foreach ($plan->features as $f)
                            <li style="display:flex;gap:7px;font-size:12.5px;color:var(--ink-700);"><span style="color:var(--teal-600, #2f6f66);font-weight:800;">✓</span>{{ $f }}</li>
                        @endforeach
                    </ul>
                @endif
                <div style="margin-top:auto;padding-top:10px;border-top:1px solid rgba(16,48,43,.07);display:flex;justify-content:space-between;align-items:center;">
                    <span class="mw-dot {{ $on ? 'ok' : '' }}">{{ $on }} {{ $on === 1 ? 'client' : 'clients' }} on it</span>
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="btn ghost sm">Edit</a>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
