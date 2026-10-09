@extends('layouts.app')
@section('title', 'Calendar')
@section('content')
{{-- Admin → Calendar (2026-10-08, roadmap 75). See Admin\CalendarController. --}}

<div class="dash-head">
    <h1>Calendar</h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <form method="GET" action="{{ route('admin.calendar') }}" style="display:flex;gap:6px;">
            <input type="hidden" name="view" value="{{ $cal['view'] }}">
            <input type="hidden" name="date" value="{{ $cal['anchor']->toDateString() }}">
            <select name="therapist" class="mw-field" style="width:220px;" onchange="this.form.submit()">
                <option value="">All therapists</option>
                @foreach ($therapists as $t)
                    <option value="{{ $t->id }}" {{ $filter === $t->id ? 'selected' : '' }}>{{ $t->user?->name }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.schedule-requests.index') }}" class="btn {{ $pendingCount ? 'primary' : 'ghost' }} sm">Requests{{ $pendingCount ? ' ('.$pendingCount.')' : '' }}</a>
    </div>
</div>

@if ($errors->any())
    <div class="mw-alert danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
@endif

<div style="display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start;" class="mw-cal-layout">
    <div>
        @include('calendar._grid', ['cal' => $cal, 'calRoute' => 'admin.calendar', 'calQuery' => $filter !== '' ? ['therapist' => $filter] : [], 'focus' => $focus])
    </div>
    <div>
        @include('calendar._panel', ['ev' => $ev, 'viewer' => 'admin', 'money' => $money, 'clashes' => $clashes])
    </div>
</div>
<style>@media (max-width: 960px) { .mw-cal-layout { grid-template-columns: 1fr !important; } }</style>
@endsection
