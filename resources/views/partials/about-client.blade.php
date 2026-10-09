{{-- What the client told us in "About you" (2026-10-09, roadmap 63). For therapist and admin
     screens only. @include('partials.about-client', ['profileId' => $id, 'compact' => false]) --}}
@php
    $mwA = \App\Models\ClientIntakeDetail::for($profileId ?? null);
    $mwCompact = $compact ?? false;
    $mwMoodColors = [1 => '#E9A9A9', 2 => '#F2C4A0', 3 => '#F3DC9A', 4 => '#BCDDBF', 5 => '#86C7AE'];
    $mwSkippedNames = $mwA ? collect((array) $mwA->skipped)->map(fn ($k) => mb_strtolower(\App\Support\IntakeQuestions::SECTIONS[$k]['label'] ?? $k))->all() : [];
@endphp
@if (! \App\Models\ClientIntakeDetail::tableReady())
    {{-- migration not run yet: show nothing --}}
@elseif (! $mwA || ! $mwA->hasAnswers())
    <p style="font-size:12.5px;color:var(--ink-500);margin:0;">
        @if (! $mwA)
            Not filled in yet — this client did their intake before these questions existed. They're asked once on their dashboard.
        @elseif ($mwA->dismissed_at)
            The client chose "Not now" on {{ $mwA->dismissed_at->format('j M Y') }}. They can add it any time from My details.
        @else
            The client skipped these questions.
        @endif
    </p>
@else
    <div class="mw-about-view {{ $mwCompact ? 'compact' : '' }}">
        @if ($mwA->topics || $mwA->own_words)
            <div class="blk">
                <div class="lb">What brings them</div>
                @if ($mwA->topics)
                    <div class="tags">@foreach ($mwA->topics as $t)<span>{{ $t }}</span>@endforeach</div>
                @endif
                @if ($mwA->own_words)<div class="q">“{{ $mwA->own_words }}”</div>@endif
            </div>
        @endif
        @if ($mwA->mood_now)
            <div class="blk">
                <div class="lb">Mood when they started</div>
                <div class="v mood"><i style="background:{{ $mwMoodColors[$mwA->mood_now] ?? '#ccc' }};"></i>{{ $mwA->moodLabel() }}</div>
            </div>
        @endif
        @if ($mwA->counselling_before || $mwA->medication || $mwA->background_notes)
            <div class="blk">
                <div class="lb">Background</div>
                @if ($mwA->counselling_before)<div class="v">Counselling before: <strong>{{ $mwA->counsellingLabel() }}</strong></div>@endif
                @if ($mwA->medication)<div class="v">Mental-health medication: <strong>{{ $mwA->medicationLabel() }}</strong></div>@endif
                @if ($mwA->background_notes)<div class="q">“{{ $mwA->background_notes }}”</div>@endif
            </div>
        @endif
        @if ($mwA->goals || $mwA->goals_text)
            <div class="blk">
                <div class="lb">Hopes for therapy</div>
                @if ($mwA->goals)
                    <div class="tags teal">@foreach ($mwA->goals as $g)<span>{{ $g }}</span>@endforeach</div>
                @endif
                @if ($mwA->goals_text)<div class="q">“{{ $mwA->goals_text }}”</div>@endif
            </div>
        @endif
        @if ($mwA->preferenceLines())
            <div class="blk">
                <div class="lb">Preferences</div>
                <div class="v">{{ implode(' · ', $mwA->preferenceLines()) }}</div>
            </div>
        @endif
        <div class="ft">
            {{ $mwA->source === 'intake' ? 'From intake' : 'Added later' }}, {{ ($mwA->completed_at ?? $mwA->created_at)?->format('j M Y') }}{{ $mwSkippedNames ? ' · skipped: '.implode(', ', $mwSkippedNames) : '' }}
        </div>
    </div>
@endif
@once
<style>
    .mw-about-view { display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:14px 22px; }
    .mw-about-view.compact { grid-template-columns:1fr; gap:10px; }
    .mw-about-view .blk { min-width:0; }
    .mw-about-view .lb { font-size:11.5px; font-weight:800; color:var(--ink-500, #5f6f6b); text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
    .mw-about-view .tags { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:6px; }
    .mw-about-view .tags span { font-size:12px; font-weight:700; color:#8a4326; background:#FDF1EC; border-radius:999px; padding:3px 10px; }
    .mw-about-view .tags.teal span { color:#24564F; background:rgba(47,111,102,.1); }
    .mw-about-view .q { font-size:13px; color:var(--ink-900, #1d2b29); line-height:1.55; white-space:pre-wrap; }
    .mw-about-view .v { font-size:13px; color:var(--ink-700, #3a4a47); line-height:1.6; }
    .mw-about-view .v.mood { display:flex; align-items:center; gap:7px; }
    .mw-about-view .v i { width:11px; height:11px; border-radius:50%; display:inline-block; }
    .mw-about-view .ft { grid-column:1 / -1; font-size:11px; color:var(--ink-300, #98a5a1); }
</style>
@endonce
