@extends('layouts.app')
@section('title', 'My progress')
@section('content')
{{-- Added 2026-10-03, see ProgressController / App\Models\CheckIn. Client wording is
     deliberately gentle (\App\Support\Screeners::clientWord): no scores, no clinical labels. 2026-11-01 refresh: summary tiles, brand colours. --}}
@include('partials.mw-trend-chart-styles')
@php
    $dirText = fn (?string $d) => match ($d) {
        'lower' => 'lower than last time',
        'higher' => 'a little higher than last time',
        'same' => 'about the same as last time',
        default => null,
    };
    $whenText = fn ($p) => $p['at']->format('j M').', '.($p['source'] === 'intake' ? 'first assessment' : 'check-in');
    $clientGrid = [0 => '', 5 => 'mild', 10 => 'moderate', 15 => 'quite high'];
@endphp

<style>
    .mwp-tiles { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:16px; margin-bottom:18px; }
    .mwp-tile { padding:20px; display:flex; flex-direction:column; gap:6px; }
    .mwp-tile .k { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:var(--ink-500); }
    .mwp-tile .k i { width:10px; height:10px; border-radius:50%; }
    .mwp-tile .v { font-size:22px; font-weight:800; color:var(--ink-900); letter-spacing:-.01em; text-transform:capitalize; }
    .mwp-chip { display:inline-flex; align-items:center; gap:5px; align-self:flex-start; font-size:12px; font-weight:800; padding:4px 10px; border-radius:999px; background:#F0F3F7; color:var(--ink-500); }
    .mwp-chip.good { background:var(--mw-green-tint, #DDF5EC); color:var(--mw-green, #00A87C); }
    .mwp-chip.watch { background:#FFF1E0; color:#B25C00; }
    .mwp-chip svg { width:13px; height:13px; }
    .mwp-charts { display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:24px 32px; margin-top:14px; }
    @media (max-width: 900px) { .mwp-tiles { grid-template-columns:1fr; } }
</style>

<div class="dash-head">
    <div style="display:flex;align-items:center;gap:12px;margin-right:auto;">
        @include('partials.mw-icon-badge', ['size' => 44, 'margin' => '0', 'tint' => '#F1E8FB', 'icon' => '<path d="M4 19h16"/><path d="M5 15l4-4 3 3 6-7"/><path d="M15 7h3v3"/>'])
        <div>
            <h1>My progress</h1>
            <div class="mwc-sub" style="margin-top:2px;">How things are changing, from your check-ins and daily moods.</div>
        </div>
    </div>
    <a href="{{ route('journal.index') }}" class="btn ghost sm" style="border-radius:999px;">My journal</a>
</div>

@if (! $status['eligible'])
    <div class="card" style="text-align:center;padding:44px 24px;">
        @include('partials.mw-illustration', ['variant' => 'sprout', 'size' => 120])
        <h2 style="font-size:18px;font-weight:800;margin:14px 0 8px;color:var(--ink-900);">Your progress will show here</h2>
        <p style="font-size:13.5px;color:var(--ink-500);max-width:380px;margin:0 auto 18px;">
            It starts with your first assessment. After that, a short check-in every two weeks shows how things are changing.
        </p>
        <a href="{{ route('intake.show') }}" class="btn coral" style="border-radius:999px;padding:11px 24px;">Start my assessment</a>
    </div>
@else
    @if ($status['due'])
        <div class="mw-note event" style="margin-bottom:18px;">
            <div class="mw-note-body">
                <div class="mw-note-title">Time for your check-in</div>
                <div class="mw-note-text">It takes about 3 minutes. The questions are the same as your first assessment, so you can see what is changing.</div>
            </div>
            <div class="mw-note-act"><a href="{{ route('progress.check-in') }}" class="mw-pill-btn filled">Start check-in</a></div>
        </div>
    @endif

    @if ($latest)
        <div class="mwp-tiles mwc-stagger">
            @foreach ([['phq9', 'Low mood', '#034C8F'], ['gad7', 'Worry and anxiety', '#EE7800']] as [$scale, $title, $swatch])
                @php $mwDir = \App\Support\Screeners::direction($previous[$scale] ?? null, $latest[$scale]); @endphp
                <div class="card mwp-tile">
                    <div class="k"><i style="background:{{ $swatch }};"></i>{{ $title }}</div>
                    <div class="v">{{ \App\Support\Screeners::clientWord($scale, $latest[$scale]) }}</div>
                    @if ($mwDir === 'lower')
                        <span class="mwp-chip good"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M6 13l6 6 6-6"/></svg>Lower than last time</span>
                    @elseif ($mwDir === 'higher')
                        <span class="mwp-chip watch"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M6 11l6-6 6 6"/></svg>A little higher than last time</span>
                    @elseif ($mwDir === 'same')
                        <span class="mwp-chip">About the same as last time</span>
                    @else
                        <span class="mwp-chip">From your first assessment</span>
                    @endif
                </div>
            @endforeach
            <div class="card mwp-tile">
                <div class="k"><i style="background:#00A87C;"></i>Next check-in</div>
                <div class="v">{{ $status['due'] ? 'Now' : $status['due_at']->format('D j M') }}</div>
                @if ($status['due'] || $status['available'])
                    <a href="{{ route('progress.check-in') }}" class="mw-pill-btn {{ $status['due'] ? 'filled' : '' }}" style="align-self:flex-start;margin-top:2px;">{{ $status['due'] ? 'Start check-in' : 'Check in now' }}</a>
                @else
                    <span class="mwp-chip">We will remind you when it is time</span>
                @endif
            </div>
        </div>

        <div class="card" style="margin-bottom:18px;">
            <h2 class="mwc-h2" style="margin:0;">How things are going</h2>
            <div class="mwc-sub">
                @if ($baseline && $timeline->count() > 1)
                    Since your first assessment on {{ $baseline['at']->format('j M') }}, low mood went from
                    {{ \App\Support\Screeners::clientWord('phq9', $baseline['phq9']) }} to {{ \App\Support\Screeners::clientWord('phq9', $latest['phq9']) }},
                    and worry from {{ \App\Support\Screeners::clientWord('gad7', $baseline['gad7']) }} to {{ \App\Support\Screeners::clientWord('gad7', $latest['gad7']) }}.
                @else
                    Your first check-in will start your trend line.
                @endif
            </div>
            <div class="mwp-charts">
                <div>
                    <div style="font-size:13px;font-weight:800;color:var(--ink-700);margin-bottom:10px;">Low mood</div>
                    @include('partials.mw-trend-chart', [
                        'points' => $timeline->map(fn ($p) => ['at' => $p['at'], 'y' => $p['phq9'], 'tip' => $whenText($p).': '.\App\Support\Screeners::clientWord('phq9', $p['phq9'])])->all(),
                        'yMin' => 0, 'yMax' => \App\Support\Screeners::PHQ9_MAX, 'grid' => $clientGrid, 'color' => '#034C8F',
                        'label' => 'Low mood over time, lower is better',
                    ])
                </div>
                <div>
                    <div style="font-size:13px;font-weight:800;color:var(--ink-700);margin-bottom:10px;">Worry and anxiety</div>
                    @include('partials.mw-trend-chart', [
                        'points' => $timeline->map(fn ($p) => ['at' => $p['at'], 'y' => $p['gad7'], 'tip' => $whenText($p).': '.\App\Support\Screeners::clientWord('gad7', $p['gad7'])])->all(),
                        'yMin' => 0, 'yMax' => \App\Support\Screeners::GAD7_MAX, 'grid' => $clientGrid, 'color' => '#EE7800',
                        'label' => 'Worry and anxiety over time, lower is better',
                    ])
                </div>
            </div>
            <p style="font-size:12px;color:var(--ink-300);margin:12px 0 0;">Lower is better. Each dot is a check-in. Tap it to see the date.</p>
        </div>
    @endif
@endif

<div class="card" style="margin-bottom:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
        <div>
            <h2 class="mwc-h2" style="margin:0;">Daily mood</h2>
            <div class="mwc-sub">The last 60 days, from your journal. Higher is better.</div>
        </div>
        <a href="{{ route('journal.index') }}" class="mw-pill-btn">Add today's mood</a>
    </div>

    @if ($moods->isEmpty())
        <div style="display:flex;align-items:center;gap:14px;padding:6px 0;">
            @include('partials.mood-face', ['mood' => 3, 'size' => 40])
            <p style="font-size:13.5px;color:var(--ink-500);margin:0;">No moods yet. Each day you add how you are feeling in your journal, it shows up here.</p>
        </div>
    @else
        @include('partials.mw-trend-chart', [
            'points' => $moods->map(fn ($m) => ['at' => $m['at'], 'y' => $m['mood'], 'tip' => $m['at']->format('D j M').': '.($moodLabels[$m['mood']] ?? '')])->all(),
            'yMin' => 1, 'yMax' => 5, 'grid' => $moodLabels, 'color' => '#00A87C', 'height' => 150,
            'label' => 'Daily mood over the last 60 days, higher is better',
        ])
    @endif

    <form method="POST" action="{{ route('progress.share-mood') }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid rgba(3,40,80,.08);">
        @csrf
        <input type="hidden" name="share" value="{{ $shareMood ? 0 : 1 }}">
        <div style="font-size:13px;color:var(--ink-700);max-width:460px;">
            @if ($shareMood)
                <strong>Your therapist can see this chart.</strong> Only the mood, never what you write in your journal.
            @else
                <strong>This chart is private to you.</strong> You can let your therapist see it. They see only your mood, never what you write.
            @endif
        </div>
        <button type="submit" class="btn {{ $shareMood ? 'ghost' : 'primary' }} sm" style="border-radius:999px;">
            {{ $shareMood ? 'Stop sharing' : 'Share with my therapist' }}
        </button>
    </form>
</div>

<p style="font-size:12px;color:var(--ink-500);line-height:1.6;">
    Your check-in answers are shared with your therapist so they can support you. If you are struggling right now,
    <a href="{{ route('urgent-help') }}" style="color:var(--danger, #c0392b);font-weight:700;">get help now</a>.
</p>
@endsection
