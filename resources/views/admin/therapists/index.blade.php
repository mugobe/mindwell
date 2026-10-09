@extends('layouts.app')
@section('title', 'Therapists')
@section('content')

{{-- 2026-10-08 redesign: page kit header, jump tabs, headed cards, avatars. --}}
<div class="mw-page-head">
    <div>
        <h1>Therapists</h1>
        <div class="sub">Approve new sign-ups, review licence changes, and choose who can be booked in person.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.therapists.create') }}" class="btn primary sm">Add therapist</a>
    </div>
</div>

<nav class="mw-tabs" aria-label="Sections">
    <a href="#pending" class="on">To approve <span class="n">{{ $pending->count() }}</span></a>
    @if ($licenseChanges->isNotEmpty())<a href="#licence">Licence changes <span class="n">{{ $licenseChanges->count() }}</span></a>@endif
    <a href="#verified">Verified <span class="n">{{ $verified->count() }}</span></a>
</nav>

<div class="card" style="padding:0;margin-bottom:22px;scroll-margin-top:80px;" id="pending">
    <div class="mw-card-head"><h2>Waiting for approval</h2><span class="pill {{ $pending->count() ? 'warn' : 'success' }}">{{ $pending->count() }} pending</span></div>
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
                <td style="padding-left:16px;">@include('partials.person', ['name' => $therapist->user->name, 'sub' => $therapist->user->email])</td>
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
            <tr><td colspan="4"><div class="mw-empty" style="padding:22px;"><div class="big">No one waiting</div>New therapist sign-ups appear here for credential checks.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($licenseChanges->isNotEmpty())
    {{-- Added 2026-10-06: licence changes therapists asked for from their profile. --}}
    <h2 id="licence" style="font-size:15px;font-weight:800;color:var(--ink-900);margin:0 0 10px;scroll-margin-top:80px;">Licence changes waiting for approval</h2>
    <div class="card" style="padding:0;margin-bottom:24px;">
        <table class="mw-table">
            <thead><tr><th style="padding-left:16px;">Therapist</th><th>Current</th><th>Requested</th><th>Asked on</th><th style="padding-right:16px;"></th></tr></thead>
            <tbody>
                @foreach ($licenseChanges as $therapist)
                    <tr>
                        <td style="padding-left:16px;">@include('partials.person', ['name' => $therapist->user->name, 'sub' => $therapist->user->email])</td>
                        <td>{{ $therapist->license_number }} · {{ $therapist->license_state }}</td>
                        <td style="font-weight:700;">{{ $therapist->pending_license_number }} · {{ $therapist->pending_license_state }}</td>
                        <td style="font-size:12.5px;">{{ \Illuminate\Support\Carbon::parse($therapist->license_change_requested_at)->format('j M Y') }}</td>
                        <td style="padding-right:16px;text-align:right;white-space:nowrap;">
                            <form method="POST" action="{{ route('admin.therapists.license.approve', $therapist) }}" style="display:inline;">@csrf<button type="submit" class="btn primary sm">Approve</button></form>
                            <form method="POST" action="{{ route('admin.therapists.license.reject', $therapist) }}" style="display:inline;">@csrf<button type="submit" class="btn ghost sm">Reject</button></form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="card" style="padding:0;scroll-margin-top:80px;" id="verified">
    <div class="mw-card-head"><h2>Verified therapists</h2><span style="font-size:12.5px;color:var(--ink-500);">{{ $verified->count() }}</span></div>
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Name</th>
                <th>License</th>
                <th>State</th>
                <th>Rate</th>
                <th style="padding-right:16px;">In person</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($verified as $therapist)
            <tr>
                <td style="padding-left:16px;">@include('partials.person', ['name' => $therapist->user->name, 'sub' => $therapist->user->email])</td>
                <td>{{ $therapist->license_number }}</td>
                <td>{{ $therapist->license_state }}</td>
                <td>{{ $therapist->hourly_rate ? config('flutterwave.currency', 'UGX').' '.number_format((float) $therapist->hourly_rate, 0) : '—' }}</td>
                <td style="padding-right:16px;white-space:nowrap;">
                    {{-- 2026-10-07: admin decides who can be booked in person. --}}
                    <form method="POST" action="{{ route('admin.therapists.in-person', $therapist) }}" style="display:inline-flex;align-items:center;gap:8px;">
                        @csrf
                        <span class="pill {{ $therapist->offers_in_person ? 'success' : '' }}">{{ $therapist->offers_in_person ? 'On' : 'Off' }}</span>
                        <button type="submit" class="btn ghost sm">{{ $therapist->offers_in_person ? 'Switch off' : 'Switch on' }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">None yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
