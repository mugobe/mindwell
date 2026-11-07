@extends('layouts.app')
@section('title', 'My therapy')
@section('content')
{{-- My therapy (2026-11-07, roadmap 69): everything about the client's therapy in one place.
     Their therapist and next session, goals (they move the progress), homework (they answer
     it), and resources the therapist sent. Data from App\Services\CarePlan::forClient(). --}}
<style>
    .mwt-grid { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:18px; align-items:start; }
    @media (max-width: 1000px) { .mwt-grid { grid-template-columns:1fr; } }
    .mwt-grid .card { margin:0 0 16px; }
    .mwt-hero { display:flex; gap:18px; align-items:center; flex-wrap:wrap; padding:22px 24px; border-radius:24px; margin-bottom:18px; background:linear-gradient(120deg, #EAF2FC 0%, #EEF8F4 55%, #FFF4E6 100%); }
    .mwt-hero .who { flex:1; min-width:200px; }
    .mwt-hero .k { font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--mw-green, #00A87C); }
    .mwt-hero .n { font-size:21px; font-weight:800; color:var(--mw-navy-900, #022F5C); margin-top:2px; }
    .mwt-hero .s { font-size:13.5px; color:var(--ink-700); margin-top:2px; }
    .mwt-hero .act { display:flex; gap:8px; flex-wrap:wrap; }
    .mwt-hero .act .btn { border-radius:999px; }
    .mwt-h { display:flex; align-items:baseline; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .mwt-h h2 { font-size:16px; font-weight:800; color:var(--ink-900); margin:0; }
    .mwt-h small { font-size:12.5px; color:var(--ink-500); font-weight:600; }
    .mwt-goal { padding:14px 0; }
    .mwt-goal + .mwt-goal { border-top:1px solid rgba(3,40,80,.08); }
    .mwt-goal .t { font-size:14.5px; font-weight:800; color:var(--ink-900); }
    .mwt-goal .d { font-size:13px; color:var(--ink-700); margin-top:3px; line-height:1.5; white-space:pre-line; }
    .mwt-bar { height:10px; border-radius:5px; background:#EDF1F6; overflow:hidden; margin-top:10px; }
    .mwt-bar i { display:block; height:100%; border-radius:5px; background:linear-gradient(90deg, #00A87C, #00B888); }
    .mwt-meta { display:flex; justify-content:space-between; gap:10px; font-size:12px; color:var(--ink-500); margin-top:5px; flex-wrap:wrap; }
    .mwt-chip { display:inline-block; font-size:11px; font-weight:800; border-radius:999px; padding:2px 9px; margin-left:6px; vertical-align:middle; }
    .mwt-chip.active { background:var(--mw-navy-tint, #E6EFF9); color:var(--mw-navy, #034C8F); }
    .mwt-chip.achieved { background:var(--mw-green-tint, #DDF5EC); color:#0E7A5A; }
    .mwt-chip.stopped { background:#F0F3F7; color:var(--ink-500); }
    .mwt-chip.overdue { background:#FDE7DC; color:#B3261E; }
    .mwt-chip.done { background:var(--mw-green-tint, #DDF5EC); color:#0E7A5A; }
    .mwt-upd summary { list-style:none; cursor:pointer; font-size:12.5px; font-weight:700; color:var(--mw-navy, #034C8F); margin-top:8px; }
    .mwt-upd summary::-webkit-details-marker { display:none; }
    .mwt-upd form { margin-top:10px; padding:12px; border-radius:14px; background:#F7F9FC; }
    .mwt-upd input[type=range] { width:100%; accent-color:#00A87C; }
    .mwt-hw { display:flex; gap:12px; align-items:center; padding:12px 0; text-decoration:none; color:inherit; }
    .mwt-hw + .mwt-hw { border-top:1px solid rgba(3,40,80,.08); }
    .mwt-hw .ic { width:38px; height:38px; flex:0 0 38px; border-radius:11px; display:grid; place-items:center; background:#FFEBD6; color:#C25E00; }
    .mwt-hw.is-done .ic { background:var(--mw-green-tint, #DDF5EC); color:#0E7A5A; }
    .mwt-hw .t { font-size:14px; font-weight:800; color:var(--ink-900); }
    .mwt-hw .s { font-size:12.5px; color:var(--ink-500); margin-top:1px; }
    .mwt-res { display:flex; gap:12px; align-items:center; padding:10px 0; text-decoration:none; color:inherit; }
    .mwt-res + .mwt-res { border-top:1px solid rgba(3,40,80,.08); }
    .mwt-res .t { font-size:13.5px; font-weight:700; color:var(--ink-900); }
    .mwt-res .s { font-size:12px; color:var(--ink-500); }
    .mwt-empty { font-size:13px; color:var(--ink-500); line-height:1.55; }
</style>

<div class="dash-head">
    <div style="margin-right:auto;">
        <h1>My therapy</h1>
        <div class="mwc-sub" style="margin-top:2px;">Your goals, things to try between sessions, and what your therapist has shared.</div>
    </div>
</div>

<section class="mwt-hero">
    @include('partials.therapist-avatar', ['therapist' => $therapist, 'size' => 64])
    <div class="who">
        <div class="k">{{ $therapist ? 'Your therapist' : 'Getting started' }}</div>
        <div class="n">{{ $therapist?->user?->name ?? 'No therapist yet' }}</div>
        <div class="s">
            @if ($nextSession)
                Next session {{ $nextSession->scheduled_at->isToday() ? 'today' : ($nextSession->scheduled_at->isTomorrow() ? 'tomorrow' : $nextSession->scheduled_at->format('l j F')) }} at {{ $nextSession->scheduled_at->format('g:i A') }}{{ $nextSession->isInPerson() ? ', in person' : ', by video' }}.
            @elseif ($therapist)
                No session booked yet. {{ $completedCount }} {{ \Illuminate\Support\Str::plural('session', $completedCount) }} so far.
            @else
                Book your first session to meet a therapist. Goals and homework will appear here.
            @endif
        </div>
    </div>
    <div class="act">
        @if ($nextSession && ! $nextSession->isInPerson())
            <a href="{{ route('sessions.room', $nextSession) }}" class="btn coral">Go to the video room</a>
        @elseif (! $nextSession)
            <a href="{{ $therapist ? route('therapists.book', $therapist) : route('therapists.browse') }}" class="btn coral">Book a session</a>
        @endif
        @if ($therapist)
            <a href="{{ route('messages.start', $therapist->id) }}" class="btn ghost">Message</a>
        @endif
    </div>
</section>

<div class="mwt-grid">
    <div>
        <section class="card" aria-labelledby="mwt-goals">
            <div class="mwt-h"><h2 id="mwt-goals">Goals</h2><small>{{ $goals->where('status', 'achieved')->count() }} of {{ $goals->count() }} achieved</small></div>
            @forelse ($goals as $goal)
                <div class="mwt-goal">
                    <div class="t">{{ $goal->title }}<span class="mwt-chip {{ $goal->status }}">{{ $goal->statusLabel() }}</span></div>
                    @if ($goal->details)<div class="d">{{ $goal->details }}</div>@endif
                    <div class="mwt-bar" role="progressbar" aria-valuenow="{{ $goal->progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress"><i style="width:{{ $goal->progress }}%;"></i></div>
                    <div class="mwt-meta">
                        <span>{{ $goal->progress }}% of the way</span>
                        @if ($goal->target_on)<span>Aim: {{ $goal->target_on->format('j M Y') }}</span>@endif
                    </div>
                    @if ($last = $goal->updates->firstWhere('note', '!=', null))
                        <div style="font-size:12.5px;color:var(--ink-500);margin-top:6px;">{{ $last->by_role === 'client' ? 'You' : 'Your therapist' }}, {{ $last->created_at->format('j M') }}: "{{ \Illuminate\Support\Str::limit($last->note, 140) }}"</div>
                    @endif
                    @if ($goal->status === 'active')
                        <details class="mwt-upd">
                            <summary>Update my progress</summary>
                            <form method="POST" action="{{ route('care.goals.progress', $goal) }}">
                                @csrf
                                <label class="mw-label" for="p-{{ $goal->id }}">How far along are you? <b data-out>{{ $goal->progress }}%</b></label>
                                <input type="range" id="p-{{ $goal->id }}" name="progress" min="0" max="100" step="10" value="{{ $goal->progress }}" oninput="this.form.querySelector('[data-out]').textContent=this.value+'%'">
                                <textarea class="mw-field" name="note" rows="2" maxlength="1000" placeholder="What helped, or what got in the way? (optional)" style="margin-top:8px;"></textarea>
                                <button type="submit" class="btn primary sm" style="border-radius:999px;margin-top:8px;">Save</button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="mwt-empty">No goals yet. You and your therapist will set some together in a session. They will appear here so you can track them.</p>
            @endforelse
        </section>

        <section class="card" aria-labelledby="mwt-hw">
            @php($mwOpen = $homework->where('status', 'assigned')->count())
            <div class="mwt-h"><h2 id="mwt-hw">Homework</h2><small>{{ $mwOpen ? $mwOpen.' to do' : 'All done' }}</small></div>
            @forelse ($homework as $hw)
                <a href="{{ route('care.homework', $hw) }}" class="mwt-hw {{ $hw->isDone() ? 'is-done' : '' }}">
                    <span class="ic" aria-hidden="true">
                        @if ($hw->isDone())
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6v3H9z"/><path d="M7 5.5H5.5v15h13v-15H17"/><path d="M9 12h6M9 15.5h4"/></svg>
                        @endif
                    </span>
                    <span style="flex:1;min-width:0;">
                        <span class="t" style="display:block;">{{ $hw->title }}
                            @if ($hw->isOverdue())<span class="mwt-chip overdue">Past due</span>@elseif ($hw->isDone())<span class="mwt-chip done">{{ $hw->reviewed_at ? 'Reviewed' : 'Sent' }}</span>@endif
                        </span>
                        <span class="s" style="display:block;">
                            {{ $hw->isDone() ? 'You sent it on '.$hw->done_at->format('j M') : ($hw->due_on ? 'Due '.$hw->due_on->format('l j M') : 'No due date') }}{{ $hw->resource ? ' · with "'.\Illuminate\Support\Str::limit($hw->resource->title, 40).'"' : '' }}
                        </span>
                    </span>
                    <span style="font-size:13px;font-weight:700;color:var(--mw-navy, #034C8F);">{{ $hw->isDone() ? 'View' : 'Open' }}</span>
                </a>
            @empty
                <p class="mwt-empty">Nothing to do right now. When your therapist gives you something to try between sessions, it will appear here.</p>
            @endforelse
        </section>
    </div>

    <aside>
        <section class="card" aria-labelledby="mwt-res">
            <div class="mwt-h"><h2 id="mwt-res">From your therapist</h2></div>
            @forelse ($shared as $s)
                <a href="{{ route('resources.show', $s->resource) }}" class="mwt-res">
                    @include('resources._icon', ['kind' => $s->resource->kind, 'size' => 36])
                    <span style="min-width:0;">
                        <span class="t" style="display:block;">{{ $s->resource->title }}</span>
                        <span class="s" style="display:block;">{{ $s->opened_at ? 'Opened' : 'New' }} · {{ $s->created_at->format('j M') }}</span>
                    </span>
                </a>
            @empty
                <p class="mwt-empty">Nothing yet. Your therapist can send you reads, worksheets and audio here.</p>
            @endforelse
            <a href="{{ route('resources.index') }}" class="btn ghost sm" style="border-radius:999px;margin-top:12px;">Browse all resources</a>
        </section>
        <a href="{{ route('progress.index') }}" class="card mwc-lift" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
            <span style="width:40px;height:40px;border-radius:12px;background:#F1E8FB;color:#7A4CC2;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><path d="M4 19h16"/><path d="M5 15l4-4 3 3 6-6"/><path d="M15 8h3v3"/></svg></span>
            <span style="flex:1;"><b style="display:block;font-size:14px;color:var(--ink-900);">My progress</b><span style="font-size:12.5px;color:var(--ink-500);">Check-ins and how things are changing</span></span>
        </a>
    </aside>
</div>
@endsection
