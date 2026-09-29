@extends('layouts.app')
@section('title', 'Add Plan')
@section('content')

<div class="dash-head">
    <h1>Add a subscription plan</h1>
    <a href="{{ route('admin.plans.index') }}" class="btn ghost sm">Back to plans</a>
</div>

<div class="card" style="max-width:520px;">
    @if ($errors->any())
        <div class="mw-alert danger">
            <ul style="margin:0;padding-left:16px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.plans.store') }}" style="display:flex;flex-direction:column;gap:16px;">
        @csrf
        <div>
            <label class="mw-label" for="name">Plan name</label>
            <input class="mw-field" type="text" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Weekly Standard" required autofocus>
        </div>
        <div style="display:flex;gap:12px;">
            <div style="flex:1;">
                <label class="mw-label" for="price">Price ({{ config('flutterwave.currency') }})</label>
                <input class="mw-field" type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price') }}" required>
            </div>
            <div style="flex:1;">
                <label class="mw-label" for="billing_interval">Billing interval</label>
                <select class="mw-field" id="billing_interval" name="billing_interval" required>
                    <option value="weekly" {{ old('billing_interval', 'weekly') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                    <option value="monthly" {{ old('billing_interval') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                </select>
            </div>
        </div>
        <div>
            <label class="mw-label" for="sessions_included">Sessions included per period</label>
            <input class="mw-field" type="number" min="1" max="100" id="sessions_included" name="sessions_included" value="{{ old('sessions_included', 1) }}" required>
            <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">How many session credits a client gets each billing period on this plan.</div>
        </div>
        <div>
            <label class="mw-label" for="features">What's included</label>
            <textarea class="mw-field" id="features" name="features" rows="4" placeholder="One item per line, e.g.&#10;Unlimited messaging&#10;1 live session / week&#10;Journal access">{{ old('features') }}</textarea>
        </div>
        <button type="submit" class="btn primary block" style="margin-top:6px;">Add plan</button>
    </form>
</div>
@endsection
