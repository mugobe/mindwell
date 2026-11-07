@extends('layouts.app')
@section('title', 'My Journal')
@section('content')
{{--
    "My journal", added 2026-09-29 (see JournalController). Daily mood check-ins plus the
    client's notepads from video sessions, newest first. Private unless the client
    switches "Share with my therapist" on for an entry.
    2026-11-01 refresh: mood faces, two columns on wide screens (write + entries on the
    left, the last two weeks on the right), entries as a timeline.
    2026-11-07: ONE MOOD PER DAY, WRITING UNLIMITED (App\Services\JournalDays). Today's face
    is pre-selected; another face changes today's mood. Entries are grouped by day: the
    day's mood (and how it changed) on top, then that day's written entries.
--}}
@php
    $moodColors = [1 => '#E5484D', 2 => '#F2994A', 3 => '#E3B341', 4 => '#3FB984', 5 => '#0E9F6E'];
    $mwDays = $recentMoods->count();
    $mwAvg = $mwDays ? (int) round($recentMoods->avg()) : null;
@endphp

<style>
    .mwj-grid { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:22px; align-items:start; }
    .mwj-side { position:sticky; top:24px; display:flex; flex-direction:column; gap:18px; }
    .mwj-faces { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; margin:14px 0 14px; }
    .mwj-face { position:relative; cursor:pointer; }
    .mwj-face input { position:absolute; opacity:0; pointer-events:none; }
    .mwj-face span { display:flex; flex-direction:column; align-items:center; gap:6px; padding:12px 4px 10px; border-radius:16px; border:1.5px solid transparent; background:#F7F9FC; font-size:12px; font-weight:700; color:var(--ink-500); transition:background .18s, border-color .18s, transform .18s; }
    .mwj-face span svg { transition:transform .25s cubic-bezier(.3,1.6,.5,1); }
    .mwj-face:hover span { background:var(--mw-navy-tint, #E6EFF9); }
    .mwj-face:hover span svg { transform:scale(1.12) rotate(-4deg); }
    .mwj-face input:checked + span { border-color:var(--teal-700); background:var(--mw-navy-tint, #E6EFF9); color:var(--ink-900); }
    .mwj-face input:checked + span svg { transform:scale(1.12); }
    .mwj-face input:focus-visible + span { outline:2px solid var(--teal-700); outline-offset:2px; }
    .mwj-switch { display:inline-flex; align-items:center; gap:10px; font-size:13px; font-weight:600; color:var(--ink-700); cursor:pointer; }
    .mwj-switch input { position:absolute; opacity:0; pointer-events:none; }
    .mwj-switch i { position:relative; width:38px; height:22px; border-radius:999px; background:#CBD3DE; transition:background .2s; flex-shrink:0; }
    .mwj-switch i::after { content:''; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.2); transition:transform .2s cubic-bezier(.2,.8,.2,1); }
    .mwj-switch input:checked + i { background:var(--teal-700); }
    .mwj-switch input:checked + i::after { transform:translateX(16px); }
    .mwj-switch input:focus-visible + i { outline:2px solid var(--teal-700); outline-offset:2px; }

    .mwj-bars { display:flex; gap:5px; align-items:flex-end; height:92px; margin-top:14px; }
    .mwj-bars div { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap:5px; height:100%; }
    .mwj-bars b { width:100%; max-width:14px; border-radius:7px; transform-origin:bottom; animation:mwjGrow .6s cubic-bezier(.2,.8,.2,1) both; }
    .mwj-bars small { font-size:10px; color:var(--ink-300); font-weight:700; }
    @keyframes mwjGrow { from { transform:scaleY(0); } to { transform:none; } }
    .mwj-stat { display:flex; align-items:center; gap:12px; }
    .mwj-stat .n { font-size:26px; font-weight:800; color:var(--ink-900); letter-spacing:-.02em; line-height:1; }
    .mwj-stat .l { font-size:12.5px; color:var(--ink-500); }

    .mwj-entry { display:flex; gap:14px; padding:18px 20px; margin-bottom:12px; }
    .mwj-entry .ic { width:40px; height:40px; flex:0 0 40px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:var(--mw-navy-tint, #E6EFF9); color:var(--teal-700); }
    .mwj-entry .ic svg { width:20px; height:20px; }
    .mwj-kind { font-size:14px; font-weight:800; color:var(--ink-900); }
    .mwj-date { font-size:12px; color:var(--ink-500); margin-top:1px; }
    .mwj-body { font-size:14px; color:var(--ink-900); white-space:pre-line; line-height:1.6; margin-top:10px; }
    .mwj-foot { display:flex; align-items:center; gap:14px; margin-top:12px; font-size:12.5px; flex-wrap:wrap; }
    .mwj-link { background:none; border:none; padding:0; font:inherit; font-size:12.5px; font-weight:700; color:var(--teal-700); cursor:pointer; }
    .mwj-link.danger { color:#B3261E; }
    .mwj-tag { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:700; padding:3px 10px; border-radius:999px; background:#F0F3F7; color:var(--ink-500); }
    .mwj-tag.shared { background:var(--mw-green-tint, #DDF5EC); color:var(--mw-green, #00A87C); }
    .mwj-day { padding:18px 20px; margin-bottom:12px; }
    .mwj-dayhead { display:flex; align-items:center; gap:14px; }
    .mwj-dayhead .t { font-size:14px; font-weight:800; color:var(--ink-900); }
    .mwj-dayhead .s { font-size:12.5px; color:var(--ink-500); margin-top:1px; }
    .mwj-swing { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:6px; font-size:12px; color:var(--ink-500); }
    .mwj-swing span { display:inline-flex; align-items:center; gap:5px; padding:2px 8px 2px 3px; border-radius:999px; background:#F7F9FC; font-weight:600; }
    .mwj-swing span svg { width:18px; height:18px; }
    .mwj-noface { width:40px; height:40px; flex:0 0 40px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:var(--mw-navy-tint, #E6EFF9); color:var(--teal-700); }
    .mwj-noface svg { width:20px; height:20px; }
    .mwj-item { border-top:1px solid var(--mwc-line, rgba(3,40,80,.08)); margin-top:14px; padding-top:14px; }
    .mwj-item-top { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .mwj-time { font-size:12.5px; font-weight:700; color:var(--ink-500); display:flex; align-items:center; gap:6px; }
    .mwj-hint { font-size:12px; color:var(--ink-500); margin-top:10px; }
    details.mwj-edit summary { list-style:none; }
    details.mwj-edit summary::-webkit-details-marker { display:none; }
    @media (max-width: 1000px) { .mwj-grid { grid-template-columns:1fr; } .mwj-side { position:static; order:-1; } }
    @media (max-width: 520px) { .mwj-faces { gap:4px; } .mwj-face span { font-size:10.5px; padding:10px 2px 8px; } }
    @media (prefers-reduced-motion: reduce) { .mwj-bars b { animation:none; } }
</style>

<div class="dash-head">
    <div style="display:flex;align-items:center;gap:12px;margin-right:auto;">
        @include('partials.mw-icon-badge', ['size' => 44, 'margin' => '0', 'tint' => 'var(--mw-green-tint, #DDF5EC)', 'icon' => '<path d="M5 4.5h10.5a3 3 0 013 3V20H8a3 3 0 01-3-3z"/><path d="M5 17a3 3 0 013-3h10.5"/><path d="M9 8.5h6"/>'])
        <div>
            <h1>My journal</h1>
            <div class="mwc-sub" style="margin-top:2px;">Private to you, unless you choose to share an entry.</div>
        </div>
    </div>
</div>

<div class="mwj-grid">
    <div>
        <div class="card" style="padding:22px;margin-bottom:24px;">
            <h2 class="mwc-h2" style="margin:0;">How are you feeling today?</h2>
            <div class="mwc-sub">
                @if ($todayMood)
                    Today you're feeling {{ strtolower($moods[$todayMood]) }}. If that changes, tap another face. You can write here as often as you like.
                @else
                    Choose a face, then add a few words if you like.
                @endif
            </div>

            <form method="POST" action="{{ route('journal.store') }}">
                @csrf
                <div class="mwj-faces" role="radiogroup" aria-label="Mood">
                    @foreach ($moods as $value => $label)
                        <label class="mwj-face">
                            <input type="radio" name="mood" value="{{ $value }}" {{ (int) old('mood', $todayMood) === $value ? 'checked' : '' }}>
                            <span>@include('partials.mood-face', ['mood' => $value, 'size' => 36]){{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('mood')<div class="mw-alert danger" style="margin:-4px 0 12px;">{{ $message }}</div>@enderror

                <textarea class="mw-field" name="body" rows="3" placeholder="What is on your mind? What went well, what was hard?">{{ old('body') }}</textarea>
                @error('body')<div class="mw-alert danger" style="margin-top:6px;">{{ $message }}</div>@enderror

                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px;flex-wrap:wrap;">
                    <label class="mwj-switch">
                        <input type="hidden" name="shared_with_therapist" value="0">
                        <input type="checkbox" name="shared_with_therapist" value="1" {{ old('shared_with_therapist') ? 'checked' : '' }}>
                        <i></i>Share with my therapist
                    </label>
                    <button type="submit" class="btn coral" id="mwj-save" style="padding-left:26px;padding-right:26px;">{{ $todayMood ? 'Save' : 'Save entry' }}</button>
                </div>
                <div class="mwj-hint">Your mood counts once a day, so your chart stays true. Writing has no limit.</div>
            </form>
            <script>
            (function () {
                var form = document.getElementById('mwj-save').form, today = {{ (int) $todayMood }};
                function label() {
                    var words = form.body.value.trim() !== '', picked = form.querySelector('input[name="mood"]:checked');
                    var mood = picked ? parseInt(picked.value, 10) : 0;
                    document.getElementById('mwj-save').textContent = words ? 'Save entry'
                        : (today && mood && mood !== today ? "Update today's mood" : (today ? 'Save' : 'Save my mood'));
                }
                form.addEventListener('input', label); form.addEventListener('change', label); label();
            })();
            </script>
        </div>

        <h2 class="mwc-h2">Your days</h2>
        @if (! $days || $days->isEmpty())
            <div class="card" style="text-align:center;padding:36px 24px;">
                @include('partials.mw-illustration', ['variant' => 'sprout', 'size' => 110])
                <div style="font-size:16px;font-weight:800;color:var(--ink-900);margin-top:8px;">Your journal is empty for now</div>
                <p style="font-size:13.5px;color:var(--ink-500);margin:6px auto 0;max-width:420px;">
                    Check in above whenever you like. Notes you write during a video session will also appear here afterwards.
                </p>
            </div>
        @else
            <div class="mwc-stagger">
                @foreach ($days as $day)
                    @php
                        $d = $day['date'];
                        $dayName = $d->isToday() ? 'Today' : ($d->isYesterday() ? 'Yesterday' : $d->format('l, j F'));
                    @endphp
                    <div class="card mwj-day">
                        <div class="mwj-dayhead">
                            @if ($day['mood'])
                                @include('partials.mood-face', ['mood' => $day['mood'], 'size' => 40])
                            @else
                                <span class="mwj-noface" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4.5h10.5a3 3 0 013 3V20H8a3 3 0 01-3-3z"/><path d="M9 8.5h6"/></svg></span>
                            @endif
                            <div style="min-width:0;">
                                <div class="t">{{ $dayName }}</div>
                                <div class="s">
                                    {{ $day['mood'] ? 'Feeling '.strtolower($moods[$day['mood']]) : 'No mood picked' }}
                                    @if ($d->isToday() || $d->isYesterday()) · {{ $d->format('j F') }} @endif
                                </div>
                                @if ($day['changes'])
                                    <div class="mwj-swing" aria-label="How your mood changed during the day">
                                        @foreach ($day['changes'] as $i => $c)
                                            @if ($i) <span style="background:none;padding:0;">&rarr;</span> @endif
                                            <span>@include('partials.mood-face', ['mood' => $c['mood'], 'size' => 18]){{ $moods[$c['mood']] }} {{ $c['at']->format('g:i A') }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($day['entries']->isEmpty())
                                    <div style="font-size:12.5px;color:var(--ink-300);margin-top:6px;">No words, just the mood.</div>
                                @endif
                            </div>
                        </div>

                        @foreach ($day['entries'] as $entry)
                            @php
                                $therapistName = $entry->therapySession?->therapistProfile?->user?->name;
                                $mwWhen = ($entry->kind === 'session' && $entry->therapySession?->scheduled_at ? $entry->therapySession->scheduled_at : $entry->created_at);
                            @endphp
                            <div class="mwj-item">
                                <div class="mwj-item-top">
                                    <div class="mwj-time">
                                        @if ($entry->kind === 'session')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/></svg>
                                            Notes from your session{{ $therapistName ? ' with '.$therapistName : '' }} · {{ $mwWhen->format('g:i A') }}
                                        @else
                                            {{ $mwWhen->format('g:i A') }}
                                        @endif
                                    </div>
                                    <span class="mwj-tag {{ $entry->shared_with_therapist ? 'shared' : '' }}">
                                        @if ($entry->shared_with_therapist)
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;"><circle cx="8" cy="9" r="3"/><circle cx="16" cy="9" r="3"/><path d="M3 19c.8-2.6 2.8-4 5-4s4.2 1.4 5 4M11 19c.8-2.6 2.8-4 5-4s4.2 1.4 5 4"/></svg>
                                            Shared
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>
                                            Private
                                        @endif
                                    </span>
                                </div>

                                <div class="mwj-body" style="margin-top:6px;">{{ $entry->body }}</div>

                                <div class="mwj-foot">
                                    <form method="POST" action="{{ route('journal.update', $entry) }}" style="display:inline;">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="shared_with_therapist" value="{{ $entry->shared_with_therapist ? 0 : 1 }}">
                                        <button type="submit" class="mwj-link">{{ $entry->shared_with_therapist ? 'Make private' : 'Share with my therapist' }}</button>
                                    </form>

                                    <details class="mwj-edit" style="display:inline;">
                                        <summary class="mwj-link">Edit</summary>
                                        <form method="POST" action="{{ route('journal.update', $entry) }}" style="margin-top:10px;width:100%;">
                                            @csrf
                                            @method('PATCH')
                                            <textarea class="mw-field" name="body" rows="4">{{ $entry->body }}</textarea>
                                            <button type="submit" class="btn primary sm" style="margin-top:8px;border-radius:999px;">Save changes</button>
                                        </form>
                                    </details>

                                    <form method="POST" action="{{ route('journal.destroy', $entry) }}" style="display:inline;margin-left:auto;" onsubmit="return confirm('Delete this entry? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="mwj-link danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            @include('partials.pager', ['paginator' => $days])
        @endif
    </div>

    <aside class="mwj-side">
        <div class="card">
            <h2 class="mwc-h2" style="margin:0;">Your last two weeks</h2>
            @if ($recentMoods->isEmpty())
                <div class="mwc-sub">Your moods will show here as you check in.</div>
            @endif
            <div class="mwj-bars" aria-label="Moods over the last 14 days">
                @for ($i = 13; $i >= 0; $i--)
                    @php
                        $day = now()->subDays($i);
                        $mood = $recentMoods[$day->toDateString()] ?? null;
                    @endphp
                    <div title="{{ $day->format('D, M j') }}{{ $mood ? ': '.$moods[$mood] : '' }}">
                        <b style="height:{{ $mood ? 14 + $mood * 13 : 6 }}px;background:{{ $mood ? $moodColors[$mood] : 'rgba(3,40,80,.08)' }};animation-delay:{{ (13 - $i) * 30 }}ms;"></b>
                        <small>{{ $day->format('D')[0] }}</small>
                    </div>
                @endfor
            </div>
            @if ($mwDays)
                <div style="display:flex;gap:18px;margin-top:16px;padding-top:14px;border-top:1px solid var(--mwc-line, rgba(3,40,80,.08));">
                    <div class="mwj-stat"><div><div class="n">{{ $mwDays }}</div><div class="l">{{ $mwDays === 1 ? 'day' : 'days' }} checked in</div></div></div>
                    <div class="mwj-stat">
                        @include('partials.mood-face', ['mood' => $mwAvg, 'size' => 34])
                        <div><div class="l">Most days</div><div style="font-size:14px;font-weight:800;color:var(--ink-900);">{{ $moods[$mwAvg] ?? '' }}</div></div>
                    </div>
                </div>
            @endif
        </div>

        <div class="mw-note shield top" style="margin:0;">
            <div class="mw-note-body">
                <div class="mw-note-title">Private by default</div>
                <div class="mw-note-text">Only you can read your journal. Your therapist sees an entry only if you share it.</div>
            </div>
        </div>
        <a href="{{ route('progress.index') }}" class="card mwc-lift" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
            <span style="width:40px;height:40px;border-radius:12px;background:#F1E8FB;color:#7A4CC2;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><path d="M4 19h16"/><path d="M5 15l4-4 3 3 6-6"/><path d="M15 8h3v3"/></svg></span>
            <span style="flex:1;"><b style="display:block;font-size:14px;color:var(--ink-900);">My progress</b><span style="font-size:12.5px;color:var(--ink-500);">Check-ins and how things are changing</span></span>
        </a>
    </aside>
</div>
@endsection
