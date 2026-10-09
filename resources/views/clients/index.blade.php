@extends('layouts.app')
@section('title', $viewer === 'admin' ? 'Clients' : 'My clients')
@section('content')
{{-- Added 2026-10-06 (roadmap 71) — shared by Therapist\ClientController (own clients
     only) and Admin\ClientController (everyone). See App\Services\ClientRecords. --}}
@php $S = \App\Support\Screeners::class; @endphp

{{-- 2026-10-08 redesign: page kit header, status tabs and search toolbar. Tabs filter the
     rows already loaded (status comes from App\Services\ClientRecords::summary). --}}
@php
    $mwTabs = ['all' => 'All', 'Crisis flag' => 'Crisis flag', 'New' => 'New', 'Active' => 'Active', 'Inactive' => 'Inactive'];
    $mwTab = array_key_exists((string) request('status'), $mwTabs) ? (string) request('status') : 'all';
    $mwCounts = ['all' => $rows->count()];
    foreach (array_keys($mwTabs) as $k) {
        if ($k !== 'all') {
            $mwCounts[$k] = $rows->filter(fn ($r) => ($r['status'][1] ?? '') === $k)->count();
        }
    }
    $mwAll = $rows;
    if ($mwTab !== 'all') {
        $rows = $rows->filter(fn ($r) => ($r['status'][1] ?? '') === $mwTab)->values();
    }
@endphp

<div class="mw-page-head">
    <div>
        <h1>{{ $viewer === 'admin' ? 'Clients' : 'My clients' }}</h1>
        <div class="sub">{{ $viewer === 'admin' ? 'Every client, with their latest scores and sessions. Opening a file is recorded in the audit log.' : 'Clients you have a session with. Open a file for their scores, notes and history.' }}</div>
    </div>
</div>

<nav class="mw-tabs" aria-label="Filter clients">
    @foreach ($mwTabs as $key => $label)
        @if ($key === 'all' || $mwCounts[$key] > 0 || $mwTab === $key)
            <a href="{{ route($searchRoute, array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search ?: null])) }}" class="{{ $mwTab === $key ? 'on' : '' }}">
                {{ $label }} <span class="n">{{ $mwCounts[$key] }}</span>
            </a>
        @endif
    @endforeach
</nav>

<form method="GET" action="{{ route($searchRoute) }}" class="mw-toolbar">
    @if ($mwTab !== 'all')<input type="hidden" name="status" value="{{ $mwTab }}">@endif
    <label class="mw-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or client ID (e.g. 0007)" autocomplete="off" aria-label="Search clients">
    </label>
    @if ($search !== '')
        <a href="{{ route($searchRoute, array_filter(['status' => $mwTab === 'all' ? null : $mwTab])) }}" class="btn ghost sm">Clear</a>
    @endif
    <span style="margin-left:auto;font-size:12.5px;color:var(--ink-500);">{{ $rows->count() }} {{ $rows->count() === 1 ? 'client' : 'clients' }}</span>
</form>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Age</th>
                <th>Main concern</th>
                <th>Last session</th>
                <th>Next session</th>
                <th>Scores</th>
                <th style="padding-right:16px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr style="cursor:pointer;" onclick="window.location='{{ route($showRoute, $row['profile']) }}'">
                    <td style="padding-left:16px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            @include('partials.avatar', ['name' => $row['name']])
                            <div>
                                <a href="{{ route($showRoute, $row['profile']) }}" style="font-weight:700;color:var(--ink-900);text-decoration:none;">{{ $row['name'] }}</a>
                                <div style="font-size:11.5px;color:var(--ink-300);">{{ $row['label'] ?? '—' }}{{ $row['gender'] ? ' · '.$row['gender'] : '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $row['age'] ?? '—' }}</td>
                    <td style="max-width:220px;font-size:12.5px;color:var(--ink-700);">{{ $row['concern'] ? \Illuminate\Support\Str::limit($row['concern'], 60) : '—' }}</td>
                    <td style="white-space:nowrap;font-size:12.5px;">{{ $row['last_at']?->format('j M Y') ?? '—' }}</td>
                    <td style="white-space:nowrap;font-size:12.5px;">
                        @if ($row['next_at'])
                            {{ $row['next_at']->format('j M, g:i A') }}
                            @if ($row['next_status'] === 'pending_approval')
                                <div style="font-size:11px;color:var(--ink-300);">awaiting approval</div>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td style="font-size:12px;color:var(--ink-700);white-space:nowrap;">
                        @if ($row['phq9'] !== null)
                            PHQ-9 {{ $row['phq9'] }} · GAD-7 {{ $row['gad7'] }}
                            <div style="font-size:11px;color:var(--ink-300);">{{ $S::band('phq9', $row['phq9']) }} / {{ $S::band('gad7', $row['gad7']) }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td style="padding-right:16px;"><span class="pill {{ $row['status'][0] }}">{{ $row['status'][1] }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c1-3.3 3.4-5 6-5s5 1.7 6 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.6c2.2.2 3.9 1.7 5 4.4"/></svg>
                            @if ($search !== '')
                                <div class="big">No clients match “{{ $search }}”</div>Try a different name or client ID.
                            @elseif ($mwTab !== 'all')
                                <div class="big">No {{ strtolower($mwTabs[$mwTab]) }} clients</div>
                            @elseif ($viewer === 'admin')
                                <div class="big">No clients yet</div>They appear here once they sign up.
                            @else
                                <div class="big">No clients yet</div>Clients appear here once a session with you has been booked.
                            @endif
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    {{ $viewer === 'admin'
        ? 'Every client. Opening a client file is recorded in the audit log.'
        : 'Only clients you have a session with appear here. Journal entries and mood stay private unless the client shares them.' }}
</p>
@endsection
