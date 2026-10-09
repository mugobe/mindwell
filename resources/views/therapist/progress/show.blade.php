@extends('layouts.app')
@section('title', 'Client progress')
@section('content')
{{-- Added 2026-10-03 — shared by Therapist\ClientProgressController and (since the same
     day's follow-up) Admin\ClientProgressController. Staff see the full scores and
     standard bands; the client's own page uses gentler words. $viewer is 'therapist' or
     'admin' — admins never see the daily mood chart (the client shares it with their
     therapist only). --}}
@include('partials.mw-trend-chart-styles')
@php
    $S = \App\Support\Screeners::class;
    $latest = $timeline->last();
    $previous = $timeline->count() > 1 ? $timeline->get($timeline->count() - 2) : null;
    $baseline = $timeline->first();
    $bandPill = fn (?string $band) => match ($band) {
        'Minimal', 'Mild' => 'success',
        'Moderate' => 'warn',
        null => '',
        default => 'danger',
    };
    $delta = function ($from, $to) {
        if ($from === null || $to === null) {
            return null;
        }
        $d = $to - $from;

        return $d === 0 ? '±0' : ($d > 0 ? '+'.$d : (string) $d);
    };
    $sourceText = fn ($p) => $p['source'] === 'intake' ? 'Intake' : 'Check-in';
@endphp

{{-- 2026-10-08 redesign (round 3): page kit head with the client's bubble. --}}
<div class="mw-page-head">
    <div style="display:flex;align-items:center;gap:14px;">
        @include('partials.avatar', ['name' => $client->user?->name ?? 'Client', 'size' => 46])
        <div>
            <h1>{{ $client->user?->name ?? 'Client' }}</h1>
            <div class="sub">Progress since intake · PHQ-9 (depression) and GAD-7 (anxiety), lower is better{{ $client->user?->clientLabel() ? ' · '.$client->user->clientLabel() : '' }}</div>
        </div>
    </div>
    <div class="actions"><a href="{{ $backUrl }}" class="btn ghost sm">{{ $backLabel }}</a></div>
</div>

@foreach ($openFlags as $flag)
    <div class="mw-alert danger" style="margin-bottom:12px;">
        <strong>Crisis flag ({{ $flag->status }}) · {{ $flag->created_at->format('M j, g:i A') }}:</strong> {{ $flag->reason }}
        {{ $viewer === 'admin'
            ? 'Make sure their therapist (or someone on the team) follows up today, then acknowledge / resolve it in Crisis Flags.'
            : 'Please follow up with this client today and coordinate with the Mindwell admin team.' }}
    </div>
@endforeach

@if (! $latest)
    <div class="card" style="color:var(--ink-500);font-size:13px;">This client hasn't completed an intake assessment or check-in yet.</div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:18px;">
        @foreach ([['phq9', 'PHQ-9 (depression)', \App\Support\Screeners::PHQ9_MAX], ['gad7', 'GAD-7 (anxiety)', \App\Support\Screeners::GAD7_MAX]] as [$scale, $title, $max])
            @php $band = $S::band($scale, $latest[$scale]); @endphp
            <div class="card">
                <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;">{{ $title }}</div>
                <div style="display:flex;align-items:baseline;gap:10px;margin-top:4px;">
                    <span style="font-size:26px;font-weight:800;color:var(--ink-900);">{{ $latest[$scale] }}<span style="font-size:13px;color:var(--ink-300);font-weight:600;">/{{ $max }}</span></span>
                    <span class="pill {{ $bandPill($band) }}">{{ $band }}</span>
                </div>
                <div style="font-size:12px;color:var(--ink-500);margin-top:4px;">
                    {{ $sourceText($latest) }}, {{ $latest['at']->format('M j') }}
                    @if ($previous) · {{ $delta($previous[$scale], $latest[$scale]) }} vs last @endif
                    @if ($timeline->count() > 1) · {{ $delta($baseline[$scale], $latest[$scale]) }} vs intake @endif
                </div>
            </div>
        @endforeach
        <div class="card">
            <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;">Check-ins</div>
            <div style="font-size:26px;font-weight:800;color:var(--ink-900);margin-top:4px;">{{ $timeline->where('source', 'check-in')->count() }}</div>
            <div style="font-size:12px;color:var(--ink-500);margin-top:4px;">
                @if ($status['eligible'])
                    Next due {{ $status['due_at']->format('M j') }}{{ $status['due'] ? ' (due now)' : '' }}
                @endif
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:18px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:22px 30px;">
            <div>
                <div style="font-size:12.5px;font-weight:700;color:var(--ink-700);margin-bottom:10px;">PHQ-9 (0–27)</div>
                @include('partials.mw-trend-chart', [
                    'points' => $timeline->map(fn ($p) => ['at' => $p['at'], 'y' => $p['phq9'], 'flag' => $p['self_harm'], 'tip' => $p['at']->format('M j').' · '.$sourceText($p).': '.$p['phq9'].' ('.$S::band('phq9', $p['phq9']).')'.($p['self_harm'] ? ' · item 9 positive' : '')])->all(),
                    'yMin' => 0, 'yMax' => \App\Support\Screeners::PHQ9_MAX,
                    'grid' => [0 => '', 5 => '5 mild', 10 => '10 moderate', 15 => '15 mod. severe', 20 => '20 severe'],
                    'color' => '#139080', 'label' => 'PHQ-9 score over time',
                ])
            </div>
            <div>
                <div style="font-size:12.5px;font-weight:700;color:var(--ink-700);margin-bottom:10px;">GAD-7 (0–21)</div>
                @include('partials.mw-trend-chart', [
                    'points' => $timeline->map(fn ($p) => ['at' => $p['at'], 'y' => $p['gad7'], 'tip' => $p['at']->format('M j').' · '.$sourceText($p).': '.$p['gad7'].' ('.$S::band('gad7', $p['gad7']).')'])->all(),
                    'yMin' => 0, 'yMax' => \App\Support\Screeners::GAD7_MAX,
                    'grid' => [0 => '', 5 => '5 mild', 10 => '10 moderate', 15 => '15 severe'],
                    'color' => '#d9693f', 'label' => 'GAD-7 score over time',
                ])
            </div>
        </div>
        <p style="font-size:11.5px;color:var(--ink-300);margin:12px 0 0;">Lower is better. A red ring marks a positive PHQ-9 item 9 (self-harm thoughts).</p>
    </div>

    <div class="card" style="padding:0;margin-bottom:18px;">
        <table class="mw-table">
            <thead>
                <tr>
                    <th style="padding-left:16px;">Date</th>
                    <th>Source</th>
                    <th>PHQ-9</th>
                    <th>GAD-7</th>
                    <th style="padding-right:16px;">Item 9</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($timeline->reverse() as $p)
                    <tr>
                        <td style="padding-left:16px;white-space:nowrap;">{{ $p['at']->format('M j, Y') }}</td>
                        <td>{{ $sourceText($p) }}</td>
                        <td>{{ $p['phq9'] }} <span style="font-size:11.5px;color:var(--ink-500);">{{ $S::band('phq9', $p['phq9']) }}</span></td>
                        <td>{{ $p['gad7'] }} <span style="font-size:11.5px;color:var(--ink-500);">{{ $S::band('gad7', $p['gad7']) }}</span></td>
                        <td style="padding-right:16px;">
                            @if ($p['self_harm'])
                                <span class="pill danger">Positive</span>
                            @else
                                <span style="color:var(--ink-300);">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="card">
    <div class="section-title">Daily mood · last 60 days</div>
    @if ($viewer === 'admin')
        <p style="font-size:12.5px;color:var(--ink-500);margin:0;">Daily mood is only visible to the client's therapist, and only when the client chooses to share it.</p>
    @elseif (! $sharesMood)
        <p style="font-size:12.5px;color:var(--ink-500);margin:0;">This client keeps their daily mood private. They can choose to share it from their My progress page.</p>
    @elseif ($moods->isEmpty())
        <p style="font-size:12.5px;color:var(--ink-500);margin:0;">Shared, but no moods recorded in the last 60 days.</p>
    @else
        @include('partials.mw-trend-chart', [
            'points' => $moods->map(fn ($m) => ['at' => $m['at'], 'y' => $m['mood'], 'tip' => $m['at']->format('D j M').': '.($moodLabels[$m['mood']] ?? '')])->all(),
            'yMin' => 1, 'yMax' => 5, 'grid' => $moodLabels, 'color' => '#139080', 'height' => 140,
            'label' => 'Client daily mood over the last 60 days, higher is better',
        ])
        <p style="font-size:11.5px;color:var(--ink-300);margin:10px 0 0;">Higher is better. Mood only — journal text is never shared from here.</p>
    @endif
</div>
@endsection
