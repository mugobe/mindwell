@extends('layouts.app')
@section('title', 'Employer Benefit')
@section('content')

<div class="dash-head" style="display:flex;align-items:center;gap:12px;justify-content:flex-start;">
    @include('partials.mw-icon-badge', ['size' => 44, 'margin' => '0', 'tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<rect x="4" y="3" width="9" height="18" rx="1"/><rect x="14" y="9" width="6" height="12" rx="1"/><path d="M7.5 7h1M10.5 7h1M7.5 11h1M10.5 11h1M7.5 15h1M10.5 15h1"/>'])
    <h1>Employer benefit</h1>
</div>

@if ($seat)
    <div class="card" style="max-width:520px;text-align:center;padding:36px 28px;">
        @include('partials.mw-icon-badge', ['tint' => 'var(--mw-sage, #DEE8CE)', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>'])
        <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;color:var(--ink-900);">
            You're covered by {{ $seat->employerAccount->company_name }}
        </h2>
        <p style="font-size:13px;color:var(--ink-500);max-width:380px;margin:0 auto 18px;">
            Your sessions are free for as long as this benefit is active. Unlike a plan, there are no credits to run out.
        </p>
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
            <span class="pill success">Active</span>
            @if ($seat->employerAccount->contract_renewal_date)
                <span class="pill teal">Covered through {{ $seat->employerAccount->contract_renewal_date->format('M j, Y') }}</span>
            @endif
        </div>
        <p style="font-size:11px;color:var(--ink-300);margin-top:18px;">
            Added on {{ $seat->assigned_at?->format('M j, Y') }}. If the benefit ends, your next booking
            will ask for payment or a plan credit, like any other booking.
        </p>
    </div>
@else
    <div style="display:grid;grid-template-columns:minmax(0,480px) minmax(0,1fr);gap:22px;align-items:start;" class="mwe-grid">
    <style>@media (max-width: 860px) { .mwe-grid { grid-template-columns: 1fr !important; } }</style>
    <div class="card" style="padding:32px 28px;">
        @if ($errors->any())
            <div class="mw-alert danger" style="margin-bottom:18px;">{{ $errors->first('seat_code') }}</div>
        @endif

        <p style="font-size:13px;color:var(--ink-500);margin-bottom:20px;">
            If your employer offers Mindwell as a staff benefit, they will give you a short code. Enter it below
            to get free sessions for as long as the benefit is active. You do not need to pay or buy a plan while it covers you.
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
            <button type="submit" class="btn coral block" style="border-radius:999px;">Use this code</button>
        </form>
    </div>
    <div class="card" style="padding:26px 26px 22px;background:linear-gradient(140deg,#EAF2FC,#EEF8F4);box-shadow:none;">
        <h2 class="mwc-h2">How it works</h2>
        <ol style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:14px;">
            @foreach ([
                ['1', 'Get your code', 'Ask your HR or staff welfare office for your Mindwell code.'],
                ['2', 'Enter it here', 'The benefit is added to your account straight away.'],
                ['3', 'Book as usual', 'Choose a therapist and a time. There is nothing to pay while the benefit is active.'],
            ] as [$n, $t, $d])
                <li style="display:flex;gap:12px;align-items:flex-start;">
                    <span style="flex:0 0 30px;height:30px;border-radius:50%;background:var(--mw-navy, #034C8F);color:#fff;font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center;">{{ $n }}</span>
                    <span><b style="display:block;font-size:14px;color:var(--ink-900);">{{ $t }}</b><span style="font-size:13px;color:var(--ink-500);">{{ $d }}</span></span>
                </li>
            @endforeach
        </ol>
        <p style="font-size:12px;color:var(--ink-500);margin:16px 0 0;">What you talk about in your sessions stays between you and your therapist.</p>
    </div>
    </div>
@endif

@endsection
