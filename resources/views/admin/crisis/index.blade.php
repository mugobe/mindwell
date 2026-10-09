@extends('layouts.app')
@section('title', 'Crisis flags')
@section('content')
{{-- Crisis flags (2026-10-08 redesign): status tabs with counts, open rows stand out,
     one main action per row. See Admin\CrisisFlagController::index(). --}}
@php
    $statusDot = ['open' => 'bad', 'acknowledged' => 'wait', 'resolved' => 'ok'];
@endphp

<div class="mw-page-head">
    <div>
        <h1>Crisis flags</h1>
        <div class="sub">Raised automatically when a client says yes to self-harm thoughts (PHQ-9 item 9) on their intake or a check-in.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.intake.index') }}" class="btn ghost sm">Intake reviews</a></div>
</div>

@if ($openCount > 0)
    <div class="mw-alert danger" style="margin-bottom:14px;">
        <strong>{{ $openCount }} open {{ $openCount === 1 ? 'flag' : 'flags' }}.</strong> Someone may be at risk. Acknowledge once a person is following up, and resolve when that follow-up is done.
    </div>
@endif

<nav class="mw-tabs" aria-label="Filter by status">
    <a href="{{ route('admin.crisis.index') }}" class="{{ $status ? '' : 'on' }}">All <span class="n">{{ $counts->sum() }}</span></a>
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.crisis.index', ['status' => $key]) }}" class="{{ $status === $key ? 'on' : '' }}">{{ $label }} <span class="n" @if ($key === 'open' && ($counts[$key] ?? 0) > 0) style="background:#C0392B;color:#fff;" @endif>{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Reason</th>
                <th>Raised</th>
                <th>Status</th>
                <th>Followed up by</th>
                <th style="padding-right:18px;text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($flags as $flag)
                <tr @if ($flag->status === 'open') style="box-shadow:inset 3px 0 0 #C0392B;background:rgba(192,57,43,.035);" @endif>
                    <td style="padding-left:18px;">
                        @include('partials.person', ['name' => $flag->client?->user?->name, 'fallback' => 'Unknown client', 'sub' => $flag->client?->user?->phone ?: $flag->client?->user?->email, 'href' => $flag->client ? route('admin.clients.show', $flag->client) : null])
                    </td>
                    <td style="max-width:280px;font-size:12.5px;color:var(--ink-700);">{{ $flag->reason }}</td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $flag->created_at->format('j M Y') }}</div>
                        <div style="font-size:11.5px;color:{{ $flag->status === 'open' ? '#C0392B' : 'var(--ink-300)' }};">{{ $flag->created_at->diffForHumans() }}</div>
                    </td>
                    <td style="white-space:nowrap;"><span class="mw-dot {{ $statusDot[$flag->status] ?? '' }}">{{ $tabs[$flag->status] ?? ucfirst($flag->status) }}</span></td>
                    <td>
                        @if ($flag->acknowledgedBy)
                            @include('partials.person', ['name' => $flag->acknowledgedBy->name, 'sub' => $flag->acknowledged_at?->format('j M, g:i A'), 'size' => 28])
                        @else
                            <span style="color:var(--ink-300);">—</span>
                        @endif
                    </td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        @if ($flag->status === 'open')
                            <form method="POST" action="{{ route('admin.crisis.acknowledge', $flag) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn primary sm">Acknowledge</button>
                            </form>
                        @elseif ($flag->status === 'acknowledged')
                            <form method="POST" action="{{ route('admin.crisis.resolve', $flag) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn primary sm">Resolve</button>
                            </form>
                        @endif
                        <details class="mw-menu" style="vertical-align:middle;margin-left:4px;">
                            <summary aria-label="More actions">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                            </summary>
                            <div class="pop">
                                @if ($flag->client_id)
                                    <a href="{{ route('admin.clients.show', $flag->client_id) }}">Open client file</a>
                                    <a href="{{ route('admin.clients.progress', $flag->client_id) }}">Progress since intake</a>
                                @endif
                                @if ($flag->status === 'open')
                                    <hr>
                                    <form method="POST" action="{{ route('admin.crisis.resolve', $flag) }}">
                                        @csrf
                                        <button type="submit">Resolve straight away</button>
                                    </form>
                                @endif
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                            <div class="big">{{ $status ? 'Nothing here' : 'No crisis flags' }}</div>
                            {{ $status === 'open' ? 'No one is waiting for follow-up right now.' : 'Flags appear here the moment a client reports self-harm thoughts.' }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $flags->links() }}</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    Check-in flags also alert the client's therapist and every admin by email and SMS. This page is for visibility and follow-up only — it doesn't contact the client itself.
</p>

<script>
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.mw-menu[open]').forEach(function (d) { if (! d.contains(e.target)) d.removeAttribute('open'); });
    });
</script>
@endsection
