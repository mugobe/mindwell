@extends('layouts.app')
@section('title', 'Referral facilities')
@section('content')
{{-- Referral facilities (2026-10-08 redesign): page head, facility bubbles, status dots, empty state. --}}

<div class="mw-page-head">
    <div>
        <h1>Referral facilities</h1>
        <div class="sub">Hospitals, rehab centres and specialists that referred clients can be sent to.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.referrals.index') }}" class="btn ghost sm">Referrals</a>
        <a href="{{ route('admin.facilities.create') }}" class="btn primary sm">Add a facility</a>
    </div>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Facility</th>
                <th>Type</th>
                <th>Phone</th>
                <th>Referrals sent</th>
                <th>Status</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($facilities as $facility)
                <tr>
                    <td style="padding-left:18px;">@include('partials.person', ['name' => $facility->name, 'sub' => $facility->address, 'href' => route('admin.facilities.edit', $facility)])</td>
                    <td>
                        @if ($facility->facility_type)
                            <span class="pill teal">{{ $facility->facility_type }}</span>
                        @else
                            <span style="color:var(--ink-300);">—</span>
                        @endif
                    </td>
                    <td style="font-size:12.5px;white-space:nowrap;">{{ $facility->phone ?? '—' }}</td>
                    <td>{{ $facility->referrals_count ?: '—' }}</td>
                    <td><span class="mw-dot {{ $facility->is_active ? 'ok' : '' }}">{{ $facility->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td style="padding-right:18px;text-align:right;"><a href="{{ route('admin.facilities.edit', $facility) }}" class="btn ghost sm">Edit</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V7l8-4 8 4v14"/><path d="M10 21v-5h4v5"/><path d="M12 8v4M10 10h4"/></svg>
                            <div class="big">No facilities yet</div>
                            Add one so referrals have somewhere real to go.
                            <div style="margin-top:10px;"><a href="{{ route('admin.facilities.create') }}" class="btn primary sm">Add a facility</a></div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    An inactive facility stays on record but isn't offered when the team assigns a new referral.
</p>
@endsection
