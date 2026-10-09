@extends('layouts.app')
@section('title', 'Confirm Plan')
@section('content')

<div class="card" style="max-width:480px;margin:24px auto;text-align:center;padding:36px 28px;">
    @include('partials.mw-icon-badge', ['tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<path d="M12 21V10"/><path d="M12 14c0-4-3-6-7-6 0 4 3 7 7 6z"/><path d="M12 11c0-3 2.5-5 6-5 0 3-2.5 5-6 5z"/>'])
    <h1 style="font-size:19px;font-weight:800;margin-bottom:6px;color:var(--ink-900);">
        {{ $activeSubscription ? 'Switch to '.$plan->name : 'Subscribe to '.$plan->name }}
    </h1>
    <p style="font-size:13.5px;color:var(--ink-500);margin-bottom:22px;">
        {{ $plan->sessions_included }} session{{ $plan->sessions_included === 1 ? '' : 's' }} included, billed
        {{ $plan->billing_interval }}.
    </p>

    <div class="card" style="background:var(--ink-050,#f7f7f8);padding:16px;margin-bottom:{{ $activeSubscription ? '14px' : '22px' }};">
        @if ($quote['applied'])
            <div style="font-size:14px;color:var(--ink-300);text-decoration:line-through;">{{ config('flutterwave.currency') }} {{ number_format($quote['original'], 0) }}</div>
        @endif
        <div style="font-size:28px;font-weight:800;color:var(--ink-900);">{{ config('flutterwave.currency') }} {{ number_format($quote['total'], 0) }}</div>
        <div style="font-size:11.5px;color:var(--ink-300);margin-top:4px;">
            per {{ $plan->billing_interval === 'monthly' ? 'month' : 'week' }}
        </div>
    </div>

    @if ($activeSubscription)
        <div class="card" style="background:var(--ink-050,#f7f7f8);padding:14px;margin-bottom:22px;text-align:left;">
            <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px;">
                Switching from {{ $activeSubscription->subscriptionPlan->name }}
            </div>
            <p style="font-size:12.5px;color:var(--ink-500);margin:0;">
                You have {{ $proration['remaining_sessions'] }} unused credit{{ $proration['remaining_sessions'] === 1 ? '' : 's' }}{{ ! empty($proration['includes_prepaid']) ? ' plus a renewal you\'ve already paid for' : '' }}
                (worth about {{ config('flutterwave.currency') }} {{ number_format($proration['remaining_value'], 0) }}). Switching converts that into
                <strong style="color:var(--ink-900);">{{ $proration['bonus_credits'] }} bonus credit{{ $proration['bonus_credits'] === 1 ? '' : 's' }}</strong>
                on top of {{ $plan->name }}'s usual {{ $plan->sessions_included }}, so you'll start with
                {{ $plan->sessions_included + $proration['bonus_credits'] }} in total.
            </p>
        </div>
    @endif

    @include('partials.voucher-box', ['quote' => $quote])

    <form method="POST" action="{{ route('subscription.subscribe', $plan) }}" style="text-align:left;">
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
        <button type="submit" class="btn coral block" style="border-radius:999px;">{{ $activeSubscription ? 'Switch with Mobile Money' : 'Subscribe with Mobile Money' }}</button>
        @endif
    </form>
    <a href="{{ route('subscription.index') }}" style="display:inline-block;margin-top:10px;font-size:12px;color:var(--ink-500);">Cancel</a>

    <p style="font-size:10.5px;color:var(--ink-300);margin-top:16px;">
        You'll get a PIN prompt on your phone from Flutterwave to approve this payment. This is currently
        running against Flutterwave's sandbox (Mobile Money only for now, no real money moves yet) — use OTP
        <strong>123456</strong> if a test prompt asks for one. Card payments are coming in a later update.
    </p>
</div>
@endsection
