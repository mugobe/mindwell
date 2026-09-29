@extends('layouts.app')
@section('title', 'Facilities')
@section('content')

<div class="dash-head">
    <h1>Referral facilities</h1>
    <a href="{{ route('admin.facilities.create') }}" class="btn primary sm">Add a facility</a>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Name</th>
                <th>Type</th>
                <th>Contact</th>
                <th>Referrals sent here</th>
                <th>Status</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($facilities as $facility)
            <tr>
                <td style="padding-left:16px;font-weight:600;color:var(--ink-900);">{{ $facility->name }}</td>
                <td>{{ $facility->facility_type ?? '—' }}</td>
                <td style="font-size:12.5px;color:var(--ink-500);">
                    {{ $facility->phone ?? '—' }}
                    @if ($facility->address)
                        <br>{{ $facility->address }}
                    @endif
                </td>
                <td>{{ $facility->referrals_count }}</td>
                <td><span class="pill {{ $facility->is_active ? 'success' : '' }}">{{ $facility->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td style="padding-right:16px;text-align:right;">
                    <a href="{{ route('admin.facilities.edit', $facility) }}" class="btn ghost sm">Edit</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="padding:16px;color:var(--ink-500);">
                    No facilities added yet — add one so referrals have somewhere real to go.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:14px;">
    An inactive facility stays on record but won't show up as an option when the routing team assigns a
    new referral.
</p>
@endsection
