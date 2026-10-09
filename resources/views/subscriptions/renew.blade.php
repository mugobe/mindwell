@extends('layouts.app')
@section('title', 'Renew Plan')
@section('content')
{{-- Added 2026-10-01 — see SubscriptionController::renewCheckout() / renew(). --}}

<div class="card" style="max-width:480px;margin:24px auto;text-align:center;padding:36px 28px;">
    @include('partials.mw-icon-badge', ['tint' => 'var(--mw-teal-tint, #DCEDE8)', 'icon' => '<path d="M20 12a8 8 0 11-2.34-5.66"/><path d="M20 4v5h-5"/>'])
    <h1 style="font-size:19px;font-weight:800;margin-bottom:6px;color:var(--ink-900);">Renew {{ $plan->name }}</h1>
    <p style="font-size:13.5px;color:var(--ink-500);margin-bottom:22px;">
        {{ $plan->sessions_included }} session{{ $plan->sessions_included === 1 ? '' : 's' }} for another
        {{ $plan->billing_interval === 'monthly' ? 'month' : 'week' }}.
    </p>

    <div class="card" style="background:var(--ink-050,#f7f7f8);padding:16px;margin-bottom:14px;">
        @if ($quote['applied'])
            <div style="font-size:14px;color:var(--ink-300);text-decoration:line-through;">{{ config('flutterwave.currency') }} {{ number_format($quote['original'], 0) }}</div>
        @endif
        <div style="font-size:28px;font-weight:800;color:var(--ink-900);">{{ config('flutterwave.currency') }} {{ number_format($quote['total'], 0) }}</div>
        <div style="font-size:11.5px;color:var(--ink-300);margin-top:4px;">
            for {{ $newPeriodStart->format('D j M') }} – {{ $plan->periodEndFrom($newPeriodStart)->copy()->subDay()->format('D j M') }}
        </div>
    </div>

    <div class="card" style="background:{{ $inGrace ? 'var(--mw-coral-tint, #FCE1D3)' : 'var(--mw-teal-tint, #DCEDE8)' }};padding:14px;margin-bottom:22px;text-align:left;border:none;">
        <p style="font-size:12.5px;color:var(--ink-700);margin:0;">
            @if ($inGrace)
                Your plan was due for renewal on {{ $subscription->current_period_end->format('D j M') }}. Renewing starts a
                fresh period <strong>today</strong> with {{ $plan->sessions_included }} credit{{ $plan->sessions_included === 1 ? '' : 's' }}.
                @if ($subscription->sessions_remaining > 0)
                    The {{ $subscription->sessions_remaining }} credit{{ $subscription->sessions_remaining === 1 ? '' : 's' }} left from last period won't carry over.
                @endif
            @else
                You keep your {{ $subscription->sessions_remaining }} current credit{{ $subscription->sessions_remaining === 1 ? '' : 's' }}
                until {{ $subscription->current_period_end->format('D j M') }}. On that day your new period starts with
                {{ $plan->sessions_included }} credit{{ $plan->sessions_included === 1 ? '' : 's' }} (unused credits don't carry over).
            @endif
        </p>
    </div>

    @include('partials.voucher-box', ['quote' => $quote])

    <form method="POST" action="{{ route('subscription.renew.pay') }}" style="text-align:left;">
        @csrf
        @if ($quote['applied'])
            <input type="hidden" name="voucher" value="{{ $quote['code'] }}">
        @endif
        @if ($quote['applied'] && $quote['total'] <= 0)
            <button type="submit" class="btn coral block" style="border-radius:999px;">Confirm — covered by your voucher</button>
        @else
        <div style="margin-bottom:14px;">
            <label class="mw-label" for="network">Mobile Money network</label>
            <select class="mw-field" id="network" name="network" required>
                <option value="MTN" {{ old('network') === 'MTN' ? 'selected' : '' }}>MTN Mobile Money</option>
                <option value="AIRTEL" {{ old('network') === 'AIRTEL' ? 'selected' : '' }}>Airtel Money</option>
            </select>
            @error('network')<div class="mw-alert danger" style="margin-top:6px;">{{ $message }}</div>@enderror
        </div>
        <div style="margin-bottom:18px;">
            <label class="mw-label" for="phone_number">Mobile Money phone number</label>
            <input class="mw-field" type="tel" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" placeholder="0772123456" required>
            @error('phone_number')<div class="mw-alert danger" style="margin-top:6px;">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn coral block" style="border-radius:999px;">Renew with Mobile Money</button>
        @endif
    </form>
    <a href="{{ route('subscription.index') }}" style="display:inline-block;margin-top:10px;font-size:12px;color:var(--ink-500);">Cancel</a>

    <p style="font-size:10.5px;color:var(--ink-300);margin-top:16px;">
        You'll get a prompt on your phone to approve this payment. Mobile Money only for now — card payments are coming in a later update.
    </p>
</div>
@endsection
