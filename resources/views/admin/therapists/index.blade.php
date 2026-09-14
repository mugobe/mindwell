@extends('layouts.app')
@section('title', 'Therapist Approvals')
@section('content')

<div class="dash-head">
    <h1>Therapist credentialing</h1>
    <span class="pill warn">{{ $pending->count() }} pending review</span>
</div>

<div class="section-title">Pending approval ({{ $pending->count() }})</div>
<div class="card" style="padding:0;margin-bottom:24px;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Name</th>
                <th>License</th>
                <th>State</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pending as $therapist)
            <tr>
                <td style="padding-left:16px;">{{ $therapist->user->name }}</td>
                <td>{{ $therapist->license_number }}</td>
                <td>{{ $therapist->license_state }}</td>
                <td style="padding-right:16px;text-align:right;">
                    <div style="display:inline-flex;gap:8px;">
                        <form method="POST" action="{{ route('admin.therapists.approve', $therapist) }}">
                            @csrf
                            <button type="submit" class="btn primary sm">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.therapists.reject', $therapist) }}">
                            @csrf
                            <button type="submit" class="btn ghost sm" style="color:var(--danger);border-color:var(--danger);">Reject</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px;color:var(--ink-500);">No pending applications.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section-title">Verified therapists ({{ $verified->count() }})</div>
<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Name</th>
                <th>License</th>
                <th>State</th>
                <th style="padding-right:16px;">Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($verified as $therapist)
            <tr>
                <td style="padding-left:16px;">{{ $therapist->user->name }}</td>
                <td>{{ $therapist->license_number }}</td>
                <td>{{ $therapist->license_state }}</td>
                <td style="padding-right:16px;">{{ $therapist->hourly_rate ? '$'.$therapist->hourly_rate : '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px;color:var(--ink-500);">None yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection