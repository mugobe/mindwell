@extends('layouts.app')
@section('title', $resource->title)
@section('content')
{{-- One resource (2026-11-07). Article text, a PDF, audio, a YouTube video (played here,
     privacy-enhanced) or a web link. Opening it marks a share from the therapist as seen. --}}
<style>
    .mwr-read { max-width:720px; }
    .mwr-read .body p { font-size:15.5px; line-height:1.75; color:var(--ink-900); margin:0 0 14px; }
    .mwr-video { position:relative; aspect-ratio:16/9; max-width:100%; border-radius:16px; overflow:hidden; background:#0b1420; }
    .mwr-video iframe { position:absolute; inset:0; width:100%; height:100%; border:0; }
</style>

<div class="mwr-read">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('resources.index') }}" class="btn ghost sm" style="border-radius:999px;margin-bottom:14px;">&larr; Back</a>

    <div style="display:flex;gap:14px;align-items:flex-start;">
        @include('resources._icon', ['kind' => $resource->kind, 'size' => 48])
        <div style="min-width:0;">
            <h1 style="font-size:26px;font-weight:800;letter-spacing:-.02em;color:var(--ink-900);margin:0;line-height:1.2;">{{ $resource->title }}</h1>
            <div style="font-size:13px;color:var(--ink-500);margin-top:4px;">{{ collect([$resource->topic, $resource->kindLabel(), $resource->lengthLabel() !== 'PDF' ? $resource->lengthLabel() : null])->filter()->join(' · ') }}</div>
        </div>
    </div>

    @if ($share)
        <div class="mw-note chat top" style="margin:16px 0 0;">
            <div class="mw-note-body">
                <div class="mw-note-title">Sent by {{ $share->therapistProfile?->user?->name ?? 'your therapist' }} on {{ $share->created_at->format('j F') }}</div>
                @if ($share->note)<div class="mw-note-text">{{ $share->note }}</div>@endif
            </div>
        </div>
    @endif

    @if ($resource->summary)
        <p style="font-size:15px;color:var(--ink-700);line-height:1.6;margin:16px 0 0;">{{ $resource->summary }}</p>
    @endif

    <div class="card" style="margin-top:16px;padding:22px;">
        @switch ($resource->kind)
            @case('article')
                <div class="body">
                    @foreach (preg_split("/\R{2,}/", trim((string) $resource->body)) as $para)
                        <p>{!! nl2br(e($para)) !!}</p>
                    @endforeach
                </div>
                @break
            @case('pdf')
                <p style="font-size:14px;color:var(--ink-700);margin:0 0 12px;">Open the worksheet to read or print it. You can fill it in on paper and send a photo to your therapist.</p>
                <a href="{{ $resource->fileUrl() }}" target="_blank" rel="noopener" class="btn primary" style="border-radius:999px;">Open the PDF</a>
                @break
            @case('audio')
                <p style="font-size:14px;color:var(--ink-700);margin:0 0 12px;">Find a quiet, comfortable place. Headphones help.</p>
                <audio controls preload="none" src="{{ $resource->fileUrl() }}" style="width:100%;"></audio>
                @break
            @case('video')
                @if ($resource->youtubeEmbed())
                    <div class="mwr-video"><iframe src="{{ $resource->youtubeEmbed() }}" title="{{ $resource->title }}" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
                @else
                    <a href="{{ $resource->url }}" target="_blank" rel="noopener" class="btn primary" style="border-radius:999px;">Watch the video</a>
                @endif
                @break
            @default
                <p style="font-size:14px;color:var(--ink-700);margin:0 0 12px;">This opens a page on another website.</p>
                <a href="{{ $resource->url }}" target="_blank" rel="noopener" class="btn primary" style="border-radius:999px;">Open the page</a>
        @endswitch
    </div>

    @if (auth()->user()->role === 'client')
        <p style="font-size:12.5px;color:var(--ink-500);margin-top:14px;">Something here brings up difficult feelings? You can talk about it with your therapist, or <a href="{{ route('urgent-help') }}">get help now</a>.</p>
    @endif
</div>
@endsection
