@extends('layouts.app')
@section('title', 'Crisis Flags')
@section('content')

<div class="dash-head">
    <h1>Crisis flags</h1>
    @if ($openCount > 0)
        <span class="pill danger">{{ $openCount }} open</span>
    @else
        <span class="pill success">All clear</span>
    @endif
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>Reason</th>
                <th>Raised</th>
                <th>Status</th>
                <th>Acknowledged by</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($flags as $flag)
            <tr>
                <td style="padding-left:16px;">
                    {{ $flag->client?->user?->name ?? 'Unknown client' }}
                    <div style="font-size:10.5px;color:var(--ink-300);">{{ $flag->client?->user?->email }}</div>
                </td>
                <td style="max-width:280px;">{{ $flag->reason }}</td>
                <td>{{ $flag->created_at->format('M j, Y g:i A') }}</td>
                <td>
                    <span class="pill {{ match($flag->status) {
                        'open' => 'danger',
                        'acknowledged' => 'warn',
                        default => 'success',
                    } }}">
                        {{ ucfirst($flag->status) }}
                    </span>
                </td>
                <td>
                    @if ($flag->acknowledgedBy)
                        {{ $flag->acknowledgedBy->name }}
                        <div style="font-size:10.5px;color:var(--ink-300);">{{ $flag->acknowledged_at?->format('M j, g:i A') }}</div>
                    @else
                        —
                    @endif
                </td>
                <td style="padding-right:16px;text-align:right;">
                    <div style="display:inline-flex;gap:8px;">
                        @if ($flag->status === 'open')
                            <form method="POST" action="{{ route('admin.crisis.acknowledge', $flag) }}">
                                @csrf
                                <button type="submit" class="btn ghost sm">Acknowledge</button>
                            </form>
                        @endif
                        @if ($flag->status !== 'resolved')
                            <form method="POST" action="{{ route('admin.crisis.resolve', $flag) }}">
                                @csrf
                                <button type="submit" class="btn primary sm">Resolve</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:16px;color:var(--ink-500);">No crisis flags raised yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $flags->links() }}</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    A flag is created automatically whenever a client's intake answers "yes" to self-harm ideation (PHQ-9 item 9).
    Acknowledge it once someone is actively following up, and resolve it once that follow-up is complete.
    This is a visibility tool only — it does not page anyone or contact the client itself.
</p>
@endsection
