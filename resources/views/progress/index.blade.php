@extends('layouts.app')
@section('title', 'My progress')
@section('content')
{{-- Added 2026-10-03 — see ProgressController / App\Models\CheckIn. Client wording is
     deliberately gentle (\App\Support\Screeners::clientWord): no scores, no clinical labels. --}}
@include('partials.mw-trend-chart-styles')
@php
    $dirText = fn (?string $d) => match ($d) {
        'lower' => 'lower than last time',
        'higher' => 'a little higher than last time',
        'same' => 'about the same as last time',
        default => null,
    };
    $whenText = fn ($p) => $p['at']->format('M j').' · '.($p['source'] === 'intake' ? 'first assessment' : 'check-in');
    $clientGrid = [0 => '', 5 => 'mild', 10 => 'moderate', 15 => 'quite high'];
@endphp

<div class="dash-head" style="align-items:center;">
    <div style="display:flex;align-items:center;gap:12px;">
        @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-sage, #DEE8CE)', 'icon' => '<path d="M4 19h16"/><path d="M5 15l4-4 3 3 6-7"/><path d="M15 7h3v3"/>'])
        <h1>My progress</h1>
    </div>
    <a href="{{ route('journal.index') }}" class="btn ghost sm" style="border-radius:999px;">My journal</a>
</div>

@if (! $status['eligible'])
    <div class="card" style="text-align:center;padding:40px 24px;">
        @include('partials.mw-illustration', ['variant' => 'sprout'])
        <h2 style="font-size:17px;font-weight:800;margin:14px 0 8px;color:var(--ink-900);">Your progress will show here</h2>
        <p style="font-size:13px;color:var(--ink-500);max-width:380px;margin:0 auto 18px;">
            It starts with your first assessment. After that, a short check-in every two weeks shows how things are changing.
        </p>
        <a href="{{ route('intake.show') }}" class="btn coral" style="border-radius:999px;padding:10px 24px;">Start my assessment</a>
    </div>
@else
    {{-- Check-in invitation --}}
    @if ($status['due'])
        <div class="card" style="margin-bottom:18px;background:var(--mw-coral-tint, #FCE1D3);border:none;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
            <div style="max-width:460px;">
                <div style="font-size:15px;font-weight:800;color:var(--ink-900);">Time for your check-in</div>
                <div style="font-size:12.5px;color:var(--ink-700);margin-top:3px;">About 3 minutes — the same questions as your first assessment, so you can see what's changing.</div>
            </div>
            <a href="{{ route('progress.check-in') }}" class="btn coral" style="border-radius:999px;padding:10px 24px;">Start check-in</a>
        </div>
    @elseif ($status['available'])
        <div class="card" style="margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div style="font-size:12.5px;color:var(--ink-700);">Your next check-in is due on <strong>{{ $status['due_at']->format('D j M') }}</strong>. You can do one now if you'd like.</div>
            <a href="{{ route('progress.check-in') }}" class="btn ghost sm" style="border-radius:999px;">Check in now</a>
        </div>
    @else
        <div class="card" style="margin-bottom:18px;font-size:12.5px;color:var(--ink-700);">
            Your next check-in is due on <strong>{{ $status['due_at']->format('D j M') }}</strong>. We'll remind you here when it's time.
        </div>
    @endif

    {{-- Check-in trend --}}
    <div class="card" style="margin-bottom:18px;">
        <div class="section-title">How things are going</div>

        @if ($latest)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px 24px;margin-bottom:6px;">
                @foreach ([['phq9', 'Low mood', '#139080'], ['gad7', 'Worry & anxiety', '#d9693f']] as [$scale, $title, $swatch])
                    @php
                        $mwChange = $dirText(\App\Support\Screeners::direction($previous[$scale] ?? null, $latest[$scale]));
                    @endphp
                    <div style="font-size:13px;color:var(--ink-700);">
                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:{{ $swatch }};margin-right:6px;"></span>
                        <strong style="color:var(--ink-900);">{{ $title }}:</strong>
                        {{ \App\Support\Screeners::clientWord($scale, $latest[$scale]) }}{{ $mwChange ? ', '.$mwChange : '' }}.
                    </div>
                @endforeach
            </div>

            @if ($baseline && $timeline->count() > 1)
                <p style="font-size:12px;color:var(--ink-500);margin:0 0 16px;">
                    Since your first assessment on {{ $baseline['at']->format('M j') }}: low mood went from
                    {{ \App\Support\Screeners::clientWord('phq9', $baseline['phq9']) }} to {{ \App\Support\Screeners::clientWord('phq9', $latest['phq9']) }},
                    and worry from {{ \App\Support\Screeners::clientWord('gad7', $baseline['gad7']) }} to {{ \App\Support\Screeners::clientWord('gad7', $latest['gad7']) }}.
                </p>
            @else
                <p style="font-size:12px;color:var(--ink-500);margin:0 0 16px;">Your first check-in will start your trend line.</p>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:22px 30px;">
                <div>
                    <div style="font-size:12.5px;font-weight:700;color:var(--ink-700);margin-bottom:10px;">Low mood</div>
                    @include('partials.mw-trend-chart', [
                        'points' => $timeline->map(fn ($p) => ['at' => $p['at'], 'y' => $p['phq9'], 'tip' => $whenText($p).': '.\App\Support\Screeners::clientWord('phq9', $p['phq9'])])->all(),
                        'yMin' => 0, 'yMax' => \App\Support\Screeners::PHQ9_MAX, 'grid' => $clientGrid, 'color' => '#139080',
                        'label' => 'Low mood over time, lower is better',
                    ])
                </div>
                <div>
                    <div style="font-size:12.5px;font-weight:700;color:var(--ink-700);margin-bottom:10px;">Worry &amp; anxiety</div>
                    @include('partials.mw-trend-chart', [
                        'points' => $timeline->map(fn ($p) => ['at' => $p['at'], 'y' => $p['gad7'], 'tip' => $whenText($p).': '.\App\Support\Screeners::clientWord('gad7', $p['gad7'])])->all(),
                        'yMin' => 0, 'yMax' => \App\Support\Screeners::GAD7_MAX, 'grid' => $clientGrid, 'color' => '#d9693f',
                        'label' => 'Worry and anxiety over time, lower is better',
                    ])
                </div>
            </div>
            <p style="font-size:11.5px;color:var(--ink-300);margin:12px 0 0;">Lower is better. Each dot is a check-in — hover or tap it for the date.</p>
        @endif
    </div>
@endif

{{-- Daily mood --}}
<div class="card" style="margin-bottom:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
        <div class="section-title" style="margin:0;">Daily mood · last 60 days</div>
        <a href="{{ route('journal.index') }}" style="font-size:12px;color:var(--teal-600,#2f6f66);font-weight:600;">Add today's mood →</a>
    </div>

    @if ($moods->isEmpty())
        <p style="font-size:12.5px;color:var(--ink-500);margin:0;">
            No moods yet. Each day you add how you're feeling in your journal, it shows up here.
        </p>
    @else
        @include('partials.mw-trend-chart', [
            'points' => $moods->map(fn ($m) => ['at' => $m['at'], 'y' => $m['mood'], 'tip' => $m['at']->format('D j M').': '.($moodLabels[$m['mood']] ?? '')])->all(),
            'yMin' => 1, 'yMax' => 5, 'grid' => $moodLabels, 'color' => '#139080', 'height' => 140,
            'label' => 'Daily mood over the last 60 days, higher is better',
        ])
        <p style="font-size:11.5px;color:var(--ink-300);margin:10px 0 0;">Higher is better.</p>
    @endif

    <form method="POST" action="{{ route('progress.share-mood') }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid rgba(0,0,0,.06);">
        @csrf
        <input type="hidden" name="share" value="{{ $shareMood ? 0 : 1 }}">
        <div style="font-size:12.5px;color:var(--ink-700);max-width:440px;">
            @if ($shareMood)
                <strong>Your therapist can see this chart.</strong> Only the mood, never what you write in your journal.
            @else
                <strong>This chart is private to you.</strong> You can let your therapist see it — only the mood, never what you write.
            @endif
        </div>
        <button type="submit" class="btn {{ $shareMood ? 'ghost' : 'primary' }} sm" style="border-radius:999px;">
            {{ $shareMood ? 'Stop sharing' : 'Share with my therapist' }}
        </button>
    </form>
</div>

<p style="font-size:11.5px;color:var(--ink-500);line-height:1.6;">
    Your check-in answers are shared with your therapist so they can support you. If you're struggling right now,
    <a href="{{ route('urgent-help') }}" style="color:var(--danger, #c0392b);font-weight:700;">get help now</a>.
</p>
@endsection
