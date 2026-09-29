@extends('layouts.app')
@section('title', 'Audit Log')
@section('content')

<div class="dash-head">
    <h1>Audit log</h1>
    <form method="GET" action="{{ route('admin.audit.index') }}" style="display:flex;gap:8px;">
        <input class="mw-field" type="text" name="action" value="{{ request('action') }}" placeholder="Filter by action, e.g. user.suspended" style="width:260px;">
        <button type="submit" class="btn ghost sm">Filter</button>
        @if (request('action'))
            <a href="{{ route('admin.audit.index') }}" class="btn ghost sm">Clear</a>
        @endif
    </form>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">When</th>
                <th>Who</th>
                <th>Action</th>
                <th style="padding-right:16px;">Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
            <tr>
                <td style="padding-left:16px;white-space:nowrap;">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                <td>{{ $log->user?->name ?? 'System' }}</td>
                <td><span class="pill teal" style="font-family:monospace;">{{ $log->action }}</span></td>
                <td style="padding-right:16px;font-size:12.5px;color:var(--ink-500);">{{ $log->description ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px;color:var(--ink-500);">No activity logged yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $logs->links() }}</div>
@endsection
