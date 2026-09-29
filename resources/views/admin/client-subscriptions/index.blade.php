@extends('layouts.app')
@section('title', 'Client Subscriptions')
@section('content')

<div class="dash-head">
    <h1>Client subscriptions</h1>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="section-title">Grant a subscription</div>
    <p style="font-size:12px;color:var(--ink-500);margin-bottom:14px;">
        Stand-in for a real checkout — use this until clients can subscribe themselves.
    </p>
    <form method="POST" action="{{ route('admin.client-subscriptions.store') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        @csrf
        <div>
            <label class="mw-label" for="user_id">Client</label>
            <select class="mw-field" id="user_id" name="user_id" required style="width:220px;">
                <option value="">Choose…</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }} ({{ $client->email }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mw-label" for="subscription_plan_id">Plan</label>
            <select class="mw-field" id="subscription_plan_id" name="subscription_plan_id" required style="width:200px;">
                <option value="">Choose…</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->sessions_included }} sessions / {{ $plan->billing_interval }})</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn primary sm" style="height:41px;">Grant</button>
    </form>
    @error('user_id')<div class="mw-error">{{ $message }}</div>@enderror
    @error('subscription_plan_id')<div class="mw-error">{{ $message }}</div>@enderror
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Plan</th>
                <th>Credits remaining</th>
                <th>Period</th>
                <th style="padding-right:16px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subscriptions as $sub)
            <tr>
                <td style="padding-left:16px;">{{ $sub->clientProfile?->user?->name ?? '—' }}</td>
                <td>{{ $sub->subscriptionPlan?->name ?? '—' }}</td>
                <td>{{ $sub->sessions_remaining }}</td>
                <td style="font-size:12px;color:var(--ink-500);">{{ $sub->current_period_start->format('M j') }} – {{ $sub->current_period_end->format('M j, Y') }}</td>
                <td style="padding-right:16px;">
                    <span class="pill {{ $sub->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($sub->status) }}</span>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">No subscriptions granted yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
