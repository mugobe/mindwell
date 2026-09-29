@extends('layouts.app')
@section('title', 'My Journal')
@section('content')
{{--
    "My journal", added 2026-09-29 — see JournalController. Daily mood check-ins plus the
    client's notepads from video sessions, newest first. Private unless the client
    switches "Share with my therapist" on for an entry.
--}}
@php
    $moodColors = [1 => '#E9A9A9', 2 => '#F2C4A0', 3 => '#F3DC9A', 4 => '#BCDDBF', 5 => '#86C7AE'];
@endphp

<style>
    .mw-journal { max-width: 760px; margin: 0 auto; }
    .mw-moods { display:flex; gap:8px; flex-wrap:wrap; margin:6px 0 14px; }
    .mw-mood input { position:absolute; opacity:0; pointer-events:none; }
    .mw-mood span { display:inline-flex; align-items:center; gap:7px; padding:8px 14px; border-radius:999px; border:1.5px solid rgba(0,0,0,.1); background:#fff; font-size:13px; font-weight:600; color:var(--ink-700); cursor:pointer; transition:all .15s; }
    .mw-mood span i { width:12px; height:12px; border-radius:50%; display:inline-block; }
    .mw-mood input:checked + span { border-color: var(--teal-600, #2f6f66); background: var(--mw-teal-tint, #DCEDE8); color: var(--ink-900); }
    .mw-mood input:focus-visible + span { outline: 2px solid var(--teal-600, #2f6f66); outline-offset: 2px; }
    .mw-strip { display:flex; gap:6px; align-items:flex-end; margin-top:10px; }
    .mw-strip div { flex:1; text-align:center; }
    .mw-strip b { display:block; margin:0 auto 4px; width:14px; border-radius:7px; }
    .mw-strip small { font-size:10px; color:var(--ink-300); }
    .mw-entry { padding:18px 20px; margin-bottom:12px; }
    .mw-entry-head { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
    .mw-entry-kind { font-size:11.5px; font-weight:700; color:var(--ink-500); text-transform:uppercase; letter-spacing:.03em; }
    .mw-entry-body { font-size:14px; color:var(--ink-900); white-space:pre-line; line-height:1.55; }
    .mw-entry-foot { display:flex; align-items:center; gap:14px; margin-top:12px; font-size:12px; flex-wrap:wrap; }
    .mw-linkbtn { background:none; border:none; padding:0; font:inherit; font-size:12px; font-weight:600; color:var(--teal-600, #2f6f66); cursor:pointer; }
    .mw-linkbtn.danger { color:#b4534b; }
    .mw-shared { display:inline-flex; align-items:center; gap:5px; font-weight:600; }
    details.mw-edit summary { list-style:none; }
    details.mw-edit summary::-webkit-details-marker { display:none; }
</style>

<div class="mw-journal">
    <div class="dash-head" style="align-items:center;">
        <div style="display:flex;align-items:center;gap:12px;">
            @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-blush, #F5DCE2)', 'icon' => '<path d="M5 4.5h10.5a3 3 0 013 3V20H8a3 3 0 01-3-3z"/><path d="M5 17a3 3 0 013-3h10.5"/><path d="M9 8.5h6"/>'])
            <h1>My journal</h1>
        </div>
    </div>

    <div class="card" style="padding:22px 22px 20px;margin-bottom:22px;">
        <div style="font-size:16px;font-weight:800;color:var(--ink-900);">How are you feeling today?</div>
        <div style="font-size:12.5px;color:var(--ink-500);margin-top:2px;">A few words is plenty. Only you can see this unless you choose to share it.</div>

        <form method="POST" action="{{ route('journal.store') }}" style="margin-top:12px;">
            @csrf
            <div class="mw-moods" role="radiogroup" aria-label="Mood">
                @foreach ($moods as $value => $label)
                    <label class="mw-mood">
                        <input type="radio" name="mood" value="{{ $value }}" {{ (int) old('mood') === $value ? 'checked' : '' }} required>
                        <span><i style="background:{{ $moodColors[$value] }};"></i>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('mood')<div class="mw-alert danger" style="margin:-4px 0 12px;">{{ $message }}</div>@enderror

            <textarea class="mw-field" name="body" rows="3" placeholder="What's on your mind? What went well, what was hard…">{{ old('body') }}</textarea>
            @error('body')<div class="mw-alert danger" style="margin-top:6px;">{{ $message }}</div>@enderror

            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;flex-wrap:wrap;">
                <label style="display:inline-flex;align-items:center;gap:8px;font-size:12.5px;color:var(--ink-700);cursor:pointer;">
                    <input type="hidden" name="shared_with_therapist" value="0">
                    <input type="checkbox" name="shared_with_therapist" value="1" {{ old('shared_with_therapist') ? 'checked' : '' }}>
                    Share with my therapist
                </label>
                <button type="submit" class="btn coral" style="border-radius:999px;padding-left:24px;padding-right:24px;">Save entry</button>
            </div>
        </form>

        @if ($recentMoods->isNotEmpty())
            <div style="border-top:1px solid rgba(0,0,0,.06);margin-top:18px;padding-top:14px;">
                <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);">Your last two weeks</div>
                <div class="mw-strip">
                    @for ($i = 13; $i >= 0; $i--)
                        @php
                            $day = now()->subDays($i);
                            $mood = $recentMoods[$day->toDateString()] ?? null;
                        @endphp
                        <div title="{{ $day->format('D, M j') }}{{ $mood ? ' — '.$moods[$mood] : '' }}">
                            <b style="height:{{ $mood ? 8 + $mood * 8 : 6 }}px;background:{{ $mood ? $moodColors[$mood] : 'rgba(0,0,0,.07)' }};"></b>
                            <small>{{ $day->format('D')[0] }}</small>
                        </div>
                    @endfor
                </div>
            </div>
        @endif
    </div>

    @if (! $entries || $entries->isEmpty())
        <div class="card" style="text-align:center;padding:34px 24px;">
            @include('partials.mw-illustration', ['variant' => 'sprout', 'size' => 110])
            <div style="font-size:15px;font-weight:800;color:var(--ink-900);margin-top:8px;">Your journal is empty for now</div>
            <p style="font-size:13px;color:var(--ink-500);margin:6px auto 0;max-width:420px;">
                Check in above whenever you like. Notes you write during a video session will also appear here afterwards.
            </p>
        </div>
    @else
        @foreach ($entries as $entry)
            @php
                $therapistName = $entry->therapySession?->therapistProfile?->user?->name;
            @endphp
            <div class="card mw-entry">
                <div class="mw-entry-head">
                    <div>
                        <div class="mw-entry-kind">
                            {{ $entry->kind === 'session' ? 'Notes from your session'.($therapistName ? ' with '.$therapistName : '') : 'Daily check-in' }}
                        </div>
                        <div style="font-size:12px;color:var(--ink-300);margin-top:2px;">
                            {{ ($entry->kind === 'session' && $entry->therapySession?->scheduled_at ? $entry->therapySession->scheduled_at : $entry->created_at)->format('l, M j · g:i A') }}
                        </div>
                    </div>
                    @if ($entry->mood)
                        <span class="pill" style="background:{{ $moodColors[$entry->mood] }};color:var(--ink-900);">{{ $entry->moodLabel() }}</span>
                    @endif
                </div>

                @if (filled($entry->body))
                    <div class="mw-entry-body">{{ $entry->body }}</div>
                @else
                    <div style="font-size:13px;color:var(--ink-300);font-style:italic;">No words, just the mood.</div>
                @endif

                <div class="mw-entry-foot">
                    <span class="mw-shared" style="color:{{ $entry->shared_with_therapist ? 'var(--teal-600, #2f6f66)' : 'var(--ink-500)' }};">
                        @if ($entry->shared_with_therapist)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;"><circle cx="8" cy="9" r="3"/><circle cx="16" cy="9" r="3"/><path d="M3 19c.8-2.6 2.8-4 5-4s4.2 1.4 5 4M11 19c.8-2.6 2.8-4 5-4s4.2 1.4 5 4"/></svg>
                            Shared with your therapist
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>
                            Private
                        @endif
                    </span>

                    <form method="POST" action="{{ route('journal.update', $entry) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="shared_with_therapist" value="{{ $entry->shared_with_therapist ? 0 : 1 }}">
                        <button type="submit" class="mw-linkbtn">{{ $entry->shared_with_therapist ? 'Make private' : 'Share with my therapist' }}</button>
                    </form>

                    <details class="mw-edit" style="display:inline;">
                        <summary class="mw-linkbtn">Edit</summary>
                        <form method="POST" action="{{ route('journal.update', $entry) }}" style="margin-top:10px;width:100%;">
                            @csrf
                            @method('PATCH')
                            <textarea class="mw-field" name="body" rows="4">{{ $entry->body }}</textarea>
                            <button type="submit" class="btn primary sm" style="margin-top:8px;border-radius:999px;">Save changes</button>
                        </form>
                    </details>

                    <form method="POST" action="{{ route('journal.destroy', $entry) }}" style="display:inline;margin-left:auto;" onsubmit="return confirm('Delete this entry? This can\'t be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="mw-linkbtn danger">Delete</button>
                    </form>
                </div>
            </div>
        @endforeach

        <div style="margin-top:8px;">{{ $entries->links('pagination::simple-default') }}</div>
    @endif
</div>
@endsection
