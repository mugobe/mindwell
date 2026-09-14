@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

@if (auth()->user()->role === 'admin')
    <div class="dash-head">
        <h1>Platform overview</h1>
        <span class="pill teal">Welcome back, {{ auth()->user()->name }}</span>
    </div>
    <div class="stat-row">
        <div class="stat-tile">
            <div class="label">Total users</div>
            <div class="value">{{ \App\Models\User::count() }}</div>
        </div>
        <div class="stat-tile">
            <div class="label">Pending credentialing</div>
            <div class="value">{{ \App\Models\TherapistProfile::where('credentials_verified', false)->count() }}</div>
        </div>
        <div class="stat-tile">
            <div class="label">Verified therapists</div>
            <div class="value">{{ \App\Models\TherapistProfile::where('credentials_verified', true)->count() }}</div>
        </div>
        <div class="stat-tile">
            <div class="label">Clients</div>
            <div class="value">{{ \App\Models\User::where('role', 'client')->count() }}</div>
        </div>
    </div>
    <div class="card">
        <div class="section-title">Quick links</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('admin.users.index') }}" class="btn ghost sm">All users</a>
            <a href="{{ route('admin.therapists.index') }}" class="btn ghost sm">Therapist approvals</a>
        </div>
    </div>
@else
    <div class="card" style="text-align:center;padding:48px 24px;">
        <div style="font-size:32px;margin-bottom:12px;">🌿</div>
        <h1 style="font-size:19px;font-weight:800;margin-bottom:8px;color:var(--ink-900);">
            Welcome, {{ auth()->user()->name }}
        </h1>
        <p style="font-size:13.5px;color:var(--ink-500);max-width:360px;margin:0 auto;">
            @if (auth()->user()->role === 'therapist')
                Your therapist portal (clients, schedule, session notes, earnings) is coming next.
            @else
                Your matches, bookings, and messages will show up here once the client portal is built.
            @endif
        </p>
    </div>
@endif

@endsection