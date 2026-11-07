@extends('layouts.app')
@section('title', 'Resources')
@section('content')
{{-- Resource library (2026-11-07, roadmap 37). Clients: what their therapist sent on top,
     then everything published, by topic. Therapists use the same page to choose what to send. --}}
<style>
    .mwr-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:14px; }
    .mwr-card { display:flex; gap:14px; align-items:flex-start; padding:16px 18px; text-decoration:none; color:inherit; margin:0; }
    .mwr-card .t { font-size:14.5px; font-weight:800; color:var(--ink-900); line-height:1.35; }
    .mwr-card .s { font-size:13px; color:var(--ink-500); margin-top:3px; line-height:1.45; }
    .mwr-card .m { font-size:11.5px; font-weight:700; color:var(--ink-500); margin-top:8px; text-transform:uppercase; letter-spacing:.04em; }
    .mwr-chips { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 16px; }
    .mwr-chips a { padding:7px 14px; border-radius:999px; background:#fff; box-shadow:0 0 0 1px rgba(3,40,80,.1); font-size:13px; font-weight:700; color:var(--ink-700); text-decoration:none; }
    .mwr-chips a.on { background:var(--mw-navy, #034C8F); color:#fff; box-shadow:none; }
    .mwr-from { font-size:12px; color:var(--mw-green, #00A87C); font-weight:700; margin-top:6px; }
    .mwr-new { display:inline-block; font-size:10.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#C25E00; background:#FFEBD6; border-radius:999px; padding:2px 8px; margin-left:6px; vertical-align:middle; }
</style>

<div class="dash-head">
    <div style="margin-right:auto;">
        <h1>Resources</h1>
        <div class="mwc-sub" style="margin-top:2px;">Short reads, worksheets, audio and videos to help between sessions.</div>
    </div>
</div>

@if ($shared->isNotEmpty())
    <h2 class="mwc-h2">From your therapist</h2>
    <div class="mwr-grid" style="margin-bottom:24px;">
        @foreach ($shared as $s)
            <a href="{{ route('resources.show', $s->resource) }}" class="card mwr-card mwc-lift">
                @include('resources._icon', ['kind' => $s->resource->kind])
                <div style="min-width:0;">
                    <div class="t">{{ $s->resource->title }}@unless ($s->opened_at)<span class="mwr-new">New</span>@endunless</div>
                    @if ($s->note)<div class="s">"{{ \Illuminate\Support\Str::limit($s->note, 110) }}"</div>@endif
                    <div class="mwr-from">From {{ $s->therapistProfile?->user?->name ?? 'your therapist' }} · {{ $s->created_at->format('j M') }}</div>
                </div>
            </a>
        @endforeach
    </div>
@endif

<form method="GET" class="mw-search" style="margin-bottom:12px;max-width:420px;">
    <input type="search" name="q" value="{{ $q }}" placeholder="Search resources" aria-label="Search resources">
    @if ($topic)<input type="hidden" name="topic" value="{{ $topic }}">@endif
</form>
@if ($topics->isNotEmpty())
    <nav class="mwr-chips" aria-label="Topics">
        <a href="{{ route('resources.index', array_filter(['q' => $q])) }}" class="{{ $topic === '' ? 'on' : '' }}">All</a>
        @foreach ($topics as $t)
            <a href="{{ route('resources.index', array_filter(['topic' => $t, 'q' => $q])) }}" class="{{ $topic === $t ? 'on' : '' }}">{{ $t }}</a>
        @endforeach
    </nav>
@endif

@if ($resources->isEmpty())
    <div class="card" style="text-align:center;padding:36px 24px;">
        @include('partials.mw-illustration', ['variant' => 'sprout', 'size' => 100])
        <div style="font-size:16px;font-weight:800;color:var(--ink-900);margin-top:8px;">{{ $q || $topic ? 'Nothing matches that' : 'Resources are coming soon' }}</div>
        <p style="font-size:13.5px;color:var(--ink-500);margin:6px auto 0;max-width:420px;">{{ $q || $topic ? 'Try another word or topic.' : 'Our team is adding helpful reads, worksheets and audio. Check back soon.' }}</p>
    </div>
@else
    <div class="mwr-grid">
        @foreach ($resources as $r)
            <a href="{{ route('resources.show', $r) }}" class="card mwr-card mwc-lift">
                @include('resources._icon', ['kind' => $r->kind])
                <div style="min-width:0;">
                    <div class="t">{{ $r->title }}</div>
                    @if ($r->summary)<div class="s">{{ \Illuminate\Support\Str::limit($r->summary, 120) }}</div>@endif
                    <div class="m">{{ collect([$r->topic, $r->lengthLabel() ?? $r->kindLabel()])->filter()->join(' · ') }}</div>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
