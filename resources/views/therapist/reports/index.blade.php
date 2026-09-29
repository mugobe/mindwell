@extends('layouts.app')
@section('title', 'My Reports')
@section('content')

<div class="dash-head">
    <h1>Your session reports</h1>
    <form method="GET" action="{{ route('therapist.reports.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="text" name="client" class="mw-field" style="width:200px;" placeholder="Search by client name" value="{{ request('client') }}">
        <select class="mw-field" name="outcome" style="width:220px;">
            <option value="">All outcomes</option>
            @foreach ($outcomes as $value => $label)
                <option value="{{ $value }}" {{ request('outcome') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn ghost sm">Filter</button>
        @if (request('outcome') || request('client'))
            <a href="{{ route('therapist.reports.index') }}" class="btn ghost sm">Clear</a>
        @endif
    </form>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Closed</th>
                <th>Outcome</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reports as $session)
            <tr>
                <td style="padding-left:16px;">{{ $session->clientProfile?->user?->name ?? 'Unknown client' }}</td>
                <td>{{ $session->closed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td><span class="pill success">{{ $outcomes[$session->closure_outcome] ?? $session->closure_outcome }}</span></td>
                <td style="padding-right:16px;text-align:right;white-space:nowrap;">
                    <a href="{{ route('therapist.reports.show', $session) }}" class="btn ghost sm">View</a>
                    <a href="{{ route('therapist.reports.pdf', $session) }}" class="btn ghost sm">Download PDF</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px;color:var(--ink-500);">
                @if (request('outcome') || request('client'))
                    No reports match that search.
                @else
                    No reports yet — closed sessions you write up will show up here.
                @endif
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $reports->links() }}</div>
@endsection