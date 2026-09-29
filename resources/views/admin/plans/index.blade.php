@extends('layouts.app')
@section('title', 'Subscription Plans')
@section('content')

<div class="dash-head">
    <h1>Subscription plans</h1>
    <a href="{{ route('admin.plans.create') }}" class="btn primary sm">+ Add plan</a>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Name</th>
                <th>Price</th>
                <th>Billing</th>
                <th>Credits/period</th>
                <th>Features</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($plans as $plan)
            <tr>
                <td style="padding-left:16px;">{{ $plan->name }}</td>
                <td>{{ config('flutterwave.currency') }} {{ number_format($plan->price, 0) }}</td>
                <td><span class="pill teal">{{ ucfirst($plan->billing_interval) }}</span></td>
                <td>{{ $plan->sessions_included }}</td>
                <td style="font-size:12px;color:var(--ink-500);">
                    @if ($plan->features)
                        {{ implode(' · ', $plan->features) }}
                    @else
                        —
                    @endif
                </td>
                <td style="padding-right:16px;text-align:right;">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="btn ghost sm">Edit</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:16px;color:var(--ink-500);">No plans yet — add one to start selling subscriptions.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
