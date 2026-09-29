@extends('layouts.app')
@section('title', 'Confirm & Pay')
@section('content')

<div class="card" style="max-width:480px;margin:24px auto;text-align:center;padding:36px 28px;">
    @include('partials.mw-icon-badge', ['tint' => 'var(--mw-teal-tint, #DCEDE8)', 'icon' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>'])
    <h1 style="font-size:19px;font-weight:800;margin-bottom:6px;color:var(--ink-900);">Confirm your session</h1>
    <p style="font-size:13.5px;color:var(--ink-500);margin-bottom:22px;">
        A {{ $session->duration_minutes }}-minute session. Once this is confirmed, we'll start connecting you
        with a specialist right away.
    </p>

    <div class="card" style="background:var(--ink-050,#f7f7f8);padding:16px;margin-bottom:22px;">
        <div style="font-size:28px;font-weight:800;color:var(--ink-900);">{{ config('flutterwave.currency') }} {{ number_format($price, 0) }}</div>
        <div style="font-size:11.5px;color:var(--ink-300);margin-top:4px;">One-time payment for this session</div>
    </div>

    <form method="POST" action="{{ route('sessions.pay', $session) }}" style="text-align:left;">
        @csrf
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
        <button type="submit" class="btn coral block" style="border-radius:999px;">Pay with Mobile Money</button>
    </form>

    <p style="font-size:10.5px;color:var(--ink-300);margin-top:16px;">
        You'll get a PIN prompt on your phone from Flutterwave to approve this payment. This is currently
        running against Flutterwave's sandbox (Mobile Money only for now, no real money moves yet) — use OTP
        <strong>123456</strong> if a test prompt asks for one. Card payments are coming in a later update.
    </p>
</div>
@endsection
