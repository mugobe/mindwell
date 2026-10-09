@extends('layouts.app')
@section('title', 'Intake reviews')
@section('content')
{{-- Intake reviews (2026-10-08 redesign): risk tabs with counts, search, score bands, avatars.
     See Admin\IntakeReviewController::index(). --}}
@php
    // Standard published bands, shown under each score so nobody has to remember them.
    $phqBand = fn ($s) => $s >= 20 ? 'Severe' : ($s >= 15 ? 'Moderately severe' : ($s >= 10 ? 'Moderate' : ($s >= 5 ? 'Mild' : 'Minimal')));
    $gadBand = fn ($s) => $s >= 15 ? 'Severe' : ($s >= 10 ? 'Moderate' : ($s >= 5 ? 'Mild' : 'Minimal'));
    $meter = function ($score, $max) {
        $pct = $max ? max(0, min(100, round($score / $max * 100))) : 0;
        $col = $pct >= 70 ? '#C0392B' : ($pct >= 50 ? 'var(--mw-orange, #E8825E)' : ($pct >= 30 ? '#D9A43B' : '#6B8E4E'));

        return '<span class="mw-meter"><span style="width:'.$pct.'%;background:'.$col.';"></span></span>';
    };
    $riskPill = ['crisis' => 'danger', 'high' => 'danger', 'moderate' => 'warn', 'low' => 'success'];
@endphp

<div class="mw-page-head">
    <div>
        <h1>Intake reviews</h1>
        <div class="sub">Every client's first questionnaire, worst risk first. PHQ-9 measures depression, GAD-7 anxiety.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.crisis.index') }}" class="btn ghost sm">Crisis flags</a></div>
</div>

@if ($crisisCount > 0)
    <div class="mw-alert danger" style="margin-bottom:14px;">
        {{ $crisisCount }} {{ $crisisCount === 1 ? 'intake was' : 'intakes were' }} crisis-level. Each one opened a case in
        <a href="{{ route('admin.crisis.index') }}" style="color:inherit;font-weight:800;">Crisis flags</a> — follow up there.
    </div>
@endif

<nav class="mw-tabs" aria-label="Filter by risk">
    <a href="{{ route('admin.intake.index', array_filter(['q' => $q ?: null])) }}" class="{{ $risk ? '' : 'on' }}">All <span class="n">{{ $counts->sum() }}</span></a>
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.intake.index', array_filter(['risk' => $key, 'q' => $q ?: null])) }}" class="{{ $risk === $key ? 'on' : '' }}">{{ $label }} <span class="n">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>

<form method="GET" action="{{ route('admin.intake.index') }}" class="mw-toolbar">
    @if ($risk)<input type="hidden" name="risk" value="{{ $risk }}">@endif
    <label class="mw-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Search by client name or email" aria-label="Search intakes">
    </label>
    @if ($q !== '')
        <a href="{{ route('admin.intake.index', array_filter(['risk' => $risk])) }}" class="btn ghost sm">Clear</a>
    @endif
    <span style="margin-left:auto;font-size:12.5px;color:var(--ink-500);">{{ $assessments->total() }} {{ $assessments->total() === 1 ? 'intake' : 'intakes' }}</span>
</form>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Client</th>
                <th>Risk</th>
                <th>PHQ-9 · depression</th>
                <th>GAD-7 · anxiety</th>
                <th>Submitted</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @php \App\Models\ClientIntakeDetail::preload(collect($assessments->items())->map(fn ($a) => $a->client?->id)); @endphp
            @forelse ($assessments as $assessment)
                @php $cp = $assessment->client; $mwWhy = \App\Models\ClientIntakeDetail::for($cp?->id)?->concernLine(70); @endphp
                <tr>
                    <td style="padding-left:18px;">
                        @include('partials.person', [
                            'name' => $cp?->user?->name, 'fallback' => 'Unknown client', 'sub' => $cp?->user?->email,
                            'href' => $cp ? route('admin.clients.show', $cp) : null,
                        ])
                        @if ($mwWhy)
                            <div style="font-size:11.5px;color:#8a4326;margin:5px 0 0 42px;max-width:320px;" title="What brings them (About you)">{{ $mwWhy }}</div>
                        @endif
                    </td>
                    <td><span class="pill {{ $riskPill[$assessment->risk_level] ?? '' }}">{{ ucfirst($assessment->risk_level) }}</span></td>
                    <td style="white-space:nowrap;">
                        <div style="display:flex;align-items:center;gap:8px;"><strong style="font-variant-numeric:tabular-nums;">{{ $assessment->phq9_score }}</strong><span style="font-size:11px;color:var(--ink-300);">/ 27</span>{!! $meter($assessment->phq9_score, 27) !!}</div>
                        <div style="font-size:11.5px;color:var(--ink-500);">{{ $phqBand((int) $assessment->phq9_score) }}</div>
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="display:flex;align-items:center;gap:8px;"><strong style="font-variant-numeric:tabular-nums;">{{ $assessment->gad7_score }}</strong><span style="font-size:11px;color:var(--ink-300);">/ 21</span>{!! $meter($assessment->gad7_score, 21) !!}</div>
                        <div style="font-size:11.5px;color:var(--ink-500);">{{ $gadBand((int) $assessment->gad7_score) }}</div>
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $assessment->created_at->format('j M Y') }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">{{ $assessment->created_at->diffForHumans() }}</div>
                    </td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        @if ($cp)
                            <a href="{{ route('admin.clients.progress', $cp) }}" class="btn ghost sm">Progress</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6v3H9z"/><path d="M7 5H5v16h14V5h-2"/><path d="M8.5 13l2.5 2.5 4.5-5"/></svg>
                            <div class="big">{{ $q !== '' || $risk ? 'No intakes match' : 'No intakes yet' }}</div>
                            {{ $q !== '' || $risk ? 'Try another name or risk level.' : 'Each new client fills one in before their first session.' }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $assessments->links() }}</div>
@endsection
