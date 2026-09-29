@extends('layouts.app')
@section('title', 'Employer Benefit')
@section('content')

<div class="dash-head" style="display:flex;align-items:center;gap:12px;">
    @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<rect x="4" y="3" width="9" height="18" rx="1"/><rect x="14" y="9" width="6" height="12" rx="1"/><path d="M7.5 7h1M10.5 7h1M7.5 11h1M10.5 11h1M7.5 15h1M10.5 15h1"/>'])
    <h1>Employer benefit</h1>
</div>

@if ($seat)
    <div class="card" style="max-width:520px;text-align:center;padding:36px 28px;">
        @include('partials.mw-icon-badge', ['tint' => 'var(--mw-sage, #DEE8CE)', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>'])
        <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;color:var(--ink-900);">
            You're covered by {{ $seat->employerAccount->company_name }}
        </h2>
        <p style="font-size:13px;color:var(--ink-500);max-width:380px;margin:0 auto 18px;">
            Sessions are free for as long as this benefit stays active -- there's no credit count to run out of,
            unlike a subscription.
        </p>
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
            <span class="pill success">Active</span>
            @if ($seat->employerAccount->contract_renewal_date)
                <span class="pill teal">Covered through {{ $seat->employerAccount->contract_renewal_date->format('M j, Y') }}</span>
            @endif
        </div>
        <p style="font-size:11px;color:var(--ink-300);margin-top:18px;">
            Redeemed {{ $seat->assigned_at?->format('M j, Y') }}. If this stops being active, your next booking
            will simply ask for payment or a subscription credit like any other client's.
        </p>
    </div>
@else
    <div class="card" style="max-width:480px;padding:36px 28px;">
        @if ($errors->any())
            <div class="mw-alert danger" style="margin-bottom:18px;">{{ $errors->first('seat_code') }}</div>
        @endif

        <p style="font-size:13px;color:var(--ink-500);margin-bottom:20px;">
            If your employer has set up Mindwell as a benefit, they'll have given you a short code. Enter it below
            to get free access to sessions for as long as that benefit is active -- no payment or subscription
            needed while it covers you.
        </p>

        <form method="POST" action="{{ route('employer-seat.redeem') }}">
            @csrf
            <label for="seat_code" style="display:block;font-size:12px;font-weight:700;color:var(--ink-500);margin-bottom:6px;">
                Employer code
            </label>
            <input
                type="text"
                id="seat_code"
                name="seat_code"
                value="{{ old('seat_code') }}"
                placeholder="e.g. AB3D-9KLM"
                autocomplete="off"
                autocapitalize="characters"
                style="width:100%;box-sizing:border-box;padding:13px 16px;border-radius:999px;border:1.5px solid var(--ink-100);font-size:14px;font-weight:600;letter-spacing:.04em;text-align:center;margin-bottom:16px;"
            >
            <button type="submit" class="btn coral block" style="border-radius:999px;">Redeem code</button>
        </form>
    </div>
@endif

@endsection
