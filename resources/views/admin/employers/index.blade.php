@extends('layouts.app')
@section('title', 'Employers')
@section('content')

<div class="dash-head">
    <h1>Employer accounts</h1>
    <a href="{{ route('admin.employers.create') }}" class="btn primary sm">+ Add employer</a>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Company</th>
                <th>Contact</th>
                <th>Seats</th>
                <th>Status</th>
                <th>Renews</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employers as $employer)
            <tr>
                <td style="padding-left:16px;">{{ $employer->company_name }}</td>
                <td style="font-size:12px;color:var(--ink-500);">
                    {{ $employer->contact_name ?? '—' }}
                    @if ($employer->contact_email)
                        <div>{{ $employer->contact_email }}</div>
                    @endif
                </td>
                <td>{{ $employer->activeSeatsCount() }} / {{ $employer->seats_count }} used</td>
                <td>
                    <span class="pill {{ $employer->status === 'active' ? 'success' : 'danger' }}">
                        {{ ucfirst($employer->status) }}
                    </span>
                </td>
                <td>{{ $employer->contract_renewal_date?->format('M j, Y') ?? '—' }}</td>
                <td style="padding-right:16px;text-align:right;">
                    <a href="{{ route('admin.employers.show', $employer) }}" class="btn ghost sm">View seats</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:16px;color:var(--ink-500);">No employer accounts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
