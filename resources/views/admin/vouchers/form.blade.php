@extends('layouts.app')
@section('title', $voucher->exists ? 'Edit voucher' : 'New voucher')
@section('content')
{{-- Create / edit a voucher (2026-10-07, roadmap 25). Every rule here is optional except
     the discount and at least one "works on" box. --}}
@php
    $mwCur = config('flutterwave.currency');
    $v = $voucher;
    $val = fn (string $field, $default = null) => old($field, $v->{$field} ?? $default);
    $checked = fn (string $field) => old('_submitted') ? (bool) old($field) : (bool) $v->{$field};
@endphp

<div class="dash-head">
    <h1>{{ $v->exists ? 'Edit '.$v->code : 'New voucher' }}</h1>
    <a href="{{ route('admin.vouchers.index') }}" class="btn ghost sm">All vouchers</a>
</div>

@if ($errors->any())
    <div class="mw-alert danger" style="max-width:720px;">
        <ul style="margin:0;padding-left:16px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $v->exists ? route('admin.vouchers.update', $v) : route('admin.vouchers.store') }}" style="max-width:720px;display:flex;flex-direction:column;gap:14px;">
    @csrf
    @if ($v->exists)
        @method('PUT')
    @endif
    <input type="hidden" name="_submitted" value="1">

    <div class="card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
        <div>
            <label class="mw-label" for="code">Code</label>
            <input class="mw-field" id="code" name="code" value="{{ $val('code') }}" maxlength="40" placeholder="Leave blank to generate">
            <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">What clients type, e.g. WELCOME20. Letters, numbers, - and _.</div>
        </div>
        <div>
            <label class="mw-label" for="description">Note for the team (optional)</label>
            <input class="mw-field" id="description" name="description" value="{{ $val('description') }}" maxlength="255" placeholder="e.g. October launch offer">
        </div>
    </div>

    <div class="card">
        <div class="section-title">Discount</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;">
            <div>
                <label class="mw-label" for="discount_type">Type</label>
                <select class="mw-field" id="discount_type" name="discount_type">
                    <option value="percent" {{ $val('discount_type') === 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                    <option value="fixed" {{ $val('discount_type') === 'fixed' ? 'selected' : '' }}>Fixed amount ({{ $mwCur }})</option>
                </select>
            </div>
            <div>
                <label class="mw-label" for="discount_value">Amount off</label>
                <input class="mw-field" type="number" step="1" min="1" id="discount_value" name="discount_value" value="{{ $val('discount_value') !== null ? (float) $val('discount_value') : '' }}" required placeholder="20 = 20% or {{ $mwCur }} 20">
                <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">100% makes it free — no Mobile Money step.</div>
            </div>
            <div>
                <label class="mw-label" for="max_discount">Cap ({{ $mwCur }}, % only)</label>
                <input class="mw-field" type="number" step="1" min="1" id="max_discount" name="max_discount" value="{{ $val('max_discount') !== null ? (float) $val('max_discount') : '' }}" placeholder="No cap">
            </div>
            <div>
                <label class="mw-label" for="min_amount">Minimum price ({{ $mwCur }})</label>
                <input class="mw-field" type="number" step="1" min="0" id="min_amount" name="min_amount" value="{{ $val('min_amount') !== null ? (float) $val('min_amount') : '' }}" placeholder="Any price">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Works on</div>
        <div style="display:flex;gap:18px;flex-wrap:wrap;font-size:13px;color:var(--ink-700);">
            <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="applies_to_sessions" value="1" {{ $checked('applies_to_sessions') ? 'checked' : '' }}> Single sessions (online and in person)</label>
            <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="applies_to_plans" value="1" {{ $checked('applies_to_plans') ? 'checked' : '' }}> New plans</label>
            <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="applies_to_renewals" value="1" {{ $checked('applies_to_renewals') ? 'checked' : '' }}> Plan renewals</label>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Who can use it</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
            <div>
                <label class="mw-label" for="client_email">Only this client (email)</label>
                <input class="mw-field" type="email" id="client_email" name="client_email" value="{{ old('client_email', $v->client?->email) }}" placeholder="Anyone">
            </div>
            <div>
                <label class="mw-label" for="max_uses_per_client">Uses per client</label>
                <input class="mw-field" type="number" min="1" step="1" id="max_uses_per_client" name="max_uses_per_client" value="{{ $val('max_uses_per_client') }}" placeholder="Unlimited">
            </div>
            <div>
                <label class="mw-label" for="max_uses_total">Total uses (everyone)</label>
                <input class="mw-field" type="number" min="1" step="1" id="max_uses_total" name="max_uses_total" value="{{ $val('max_uses_total') }}" placeholder="Unlimited">
            </div>
        </div>
        <label style="display:flex;gap:6px;align-items:center;font-size:13px;color:var(--ink-700);margin-top:12px;">
            <input type="checkbox" name="new_clients_only" value="1" {{ $checked('new_clients_only') ? 'checked' : '' }}>
            New clients only (never paid before and no completed session)
        </label>
    </div>

    <div class="card">
        <div class="section-title">When</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
            <div>
                <label class="mw-label" for="starts_at">Starts</label>
                <input class="mw-field" type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', $v->starts_at?->format('Y-m-d')) }}">
                <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">Blank = straight away.</div>
            </div>
            <div>
                <label class="mw-label" for="ends_at">Ends (last day)</label>
                <input class="mw-field" type="date" id="ends_at" name="ends_at" value="{{ old('ends_at', $v->ends_at?->format('Y-m-d')) }}">
                <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">Blank = no end date.</div>
            </div>
        </div>
        <label style="display:flex;gap:6px;align-items:center;font-size:13px;color:var(--ink-700);margin-top:12px;">
            <input type="checkbox" name="is_active" value="1" {{ $checked('is_active') ? 'checked' : '' }}>
            Switched on
        </label>
    </div>

    <div>
        <button type="submit" class="btn primary">{{ $v->exists ? 'Save voucher' : 'Create voucher' }}</button>
    </div>
</form>

@if ($v->exists)
    <div class="section-title" style="margin-top:28px;">Used {{ $redemptions->count() }} {{ $redemptions->count() === 1 ? 'time' : 'times' }}</div>
    <div class="card" style="padding:0;max-width:720px;">
        <table class="mw-table">
            <thead>
                <tr>
                    <th style="padding-left:16px;">Date</th>
                    <th>Client</th>
                    <th>For</th>
                    <th>Discount</th>
                    <th style="padding-right:16px;">Receipt</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($redemptions as $r)
                    <tr>
                        <td style="padding-left:16px;font-size:12.5px;white-space:nowrap;">{{ $r->created_at->format('j M Y, g:i A') }}</td>
                        <td style="font-size:12.5px;">@include('partials.person', ['name' => $r->clientProfile?->user?->name, 'size' => 28])</td>
                        <td style="font-size:12.5px;">{{ ['session' => 'Session', 'plan' => 'New plan', 'renewal' => 'Renewal'][$r->purpose] ?? $r->purpose }}</td>
                        <td style="font-size:12.5px;">{{ $mwCur }} {{ number_format((float) $r->discount_amount, 0) }} <span style="color:var(--ink-300);">of {{ number_format((float) $r->original_amount, 0) }}</span></td>
                        <td style="padding-right:16px;">
                            @if ($r->paymentTransaction)
                                <a href="{{ route('admin.payments.receipt', $r->paymentTransaction) }}" class="btn ghost sm">{{ $r->paymentTransaction->receipt_number ?? 'Receipt' }}</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">Not used yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection
