@extends('layouts.app')
@section('title', 'Audit log')
@section('content')
{{-- Audit log (2026-10-08 redesign): area tabs, one search box, readable action names,
     entries grouped by day. See Admin\AuditLogController::index(). --}}
@php
    $areaOf = function (string $action) use ($areas) {
        foreach ($areas as $key => $a) {
            foreach ($a['prefixes'] as $p) {
                if (str_starts_with($action, $p)) {
                    return $key;
                }
            }
        }

        return null;
    };
    $tone = ['accounts' => 'c3', 'sessions' => 'c0', 'care' => 'c1', 'money' => 'c2', 'setup' => 'c4'];
    $human = fn (string $a) => ucfirst(str_replace(['_', '.'], [' ', ' · '], $a));
    $lastDay = null;
@endphp

<div class="mw-page-head">
    <div>
        <h1>Audit log</h1>
        <div class="sub">Who did what, and when. Every admin action and sensitive change is recorded here and can't be edited.</div>
    </div>
</div>

<nav class="mw-tabs" aria-label="Filter by area">
    <a href="{{ route('admin.audit.index', array_filter(['q' => $q ?: null])) }}" class="{{ $area ? '' : 'on' }}">All</a>
    @foreach ($areas as $key => $a)
        <a href="{{ route('admin.audit.index', array_filter(['area' => $key, 'q' => $q ?: null])) }}" class="{{ $area === $key ? 'on' : '' }}">{{ $a['label'] }}</a>
    @endforeach
</nav>

<form method="GET" action="{{ route('admin.audit.index') }}" class="mw-toolbar">
    @if ($area)<input type="hidden" name="area" value="{{ $area }}">@endif
    <label class="mw-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Search by person, action or detail" aria-label="Search the audit log">
    </label>
    @if ($q !== '')
        <a href="{{ route('admin.audit.index', array_filter(['area' => $area])) }}" class="btn ghost sm">Clear</a>
    @endif
    <span style="margin-left:auto;font-size:12.5px;color:var(--ink-500);">{{ number_format($logs->total()) }} {{ $logs->total() === 1 ? 'entry' : 'entries' }}</span>
</form>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;width:90px;">Time</th>
                <th>Who</th>
                <th>What happened</th>
                <th style="padding-right:18px;">Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                @php $day = $log->created_at->format('Y-m-d'); @endphp
                @if ($day !== $lastDay)
                    @php $lastDay = $day; @endphp
                    <tr class="mw-dayrow">
                        <td colspan="4" style="padding:10px 18px 6px;font-size:11.5px;font-weight:800;color:var(--ink-500);text-transform:uppercase;letter-spacing:.04em;background:rgba(16,48,43,.025);">
                            {{ $log->created_at->isToday() ? 'Today' : ($log->created_at->isYesterday() ? 'Yesterday' : $log->created_at->format('l, j F Y')) }}
                        </td>
                    </tr>
                @endif
                @php $ar = $areaOf($log->action); @endphp
                <tr>
                    <td style="padding-left:18px;white-space:nowrap;font-size:12.5px;color:var(--ink-700);font-variant-numeric:tabular-nums;">{{ $log->created_at->format('g:i A') }}</td>
                    <td>
                        @if ($log->user)
                            @include('partials.person', ['name' => $log->user->name, 'sub' => ucfirst($log->user->role ?? ''), 'size' => 28])
                        @else
                            <span class="mw-dot">System</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span class="mw-av {{ $tone[$ar] ?? 'c0' }}" style="width:8px;height:8px;flex-basis:8px;" aria-hidden="true"></span>
                            <span style="font-weight:700;color:var(--ink-900);">{{ $human($log->action) }}</span>
                        </div>
                        <div style="font-size:11px;color:var(--ink-300);font-family:ui-monospace, Menlo, Consolas, monospace;margin-left:16px;">{{ $log->action }}</div>
                    </td>
                    <td style="padding-right:18px;font-size:12.5px;color:var(--ink-700);max-width:420px;">{{ $log->description ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h4"/></svg>
                            <div class="big">{{ $q !== '' || $area ? 'Nothing matches' : 'No activity logged yet' }}</div>
                            {{ $q !== '' || $area ? 'Try another area or search.' : '' }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $logs->links() }}</div>
@endsection
