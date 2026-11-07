@extends('layouts.app')
@section('title', 'Resource library')
@section('content')
{{-- Admin → Resource library (2026-11-07, roadmap 37). Only admins add resources; clients read
     published ones; therapists send them to clients. See Admin\ResourceController. --}}
@php
    $kindIc = [
        'article' => '<path d="M6 3.5h9l3 3V20H6z"/><path d="M9 10h6M9 13.5h6M9 17h4"/>',
        'pdf' => '<path d="M6 3.5h9l3 3V20H6z"/><path d="M14.5 3.5V7H18"/>',
        'audio' => '<path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/>',
        'link' => '<path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/>',
    ];
@endphp

<div class="mw-page-head">
    <div>
        <h1>Resource library</h1>
        <div class="sub">Articles, worksheets, audio and videos for clients. Therapists can send any published one to a client.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.resources.create') }}" class="btn primary sm">Add a resource</a>
    </div>
</div>

<form method="GET" class="mw-search" style="margin-bottom:12px;max-width:420px;">
    <input type="search" name="q" value="{{ $q }}" placeholder="Search by title" aria-label="Search resources">
    @if ($topic)<input type="hidden" name="topic" value="{{ $topic }}">@endif
</form>

@if ($topics->isNotEmpty())
    <div class="mw-tabs" style="margin-bottom:14px;">
        <a href="{{ route('admin.resources.index', array_filter(['q' => $q])) }}" class="{{ $topic === '' ? 'on' : '' }}">All</a>
        @foreach ($topics as $t)
            <a href="{{ route('admin.resources.index', array_filter(['topic' => $t, 'q' => $q])) }}" class="{{ $topic === $t ? 'on' : '' }}">{{ $t }}</a>
        @endforeach
    </div>
@endif

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Resource</th>
                <th>Topic</th>
                <th>Sent to clients</th>
                <th>Status</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($resources as $r)
                <tr>
                    <td style="padding-left:18px;">
                        <div class="mw-person">
                            <span class="mw-av c0"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $kindIc[$r->kind] ?? $kindIc['article'] !!}</svg></span>
                            <div class="mw-person-t">
                                <a href="{{ route('admin.resources.edit', $r) }}" class="nm">{{ $r->title }}</a>
                                <div class="sb">{{ $r->kindLabel() }}{{ $r->lengthLabel() && $r->lengthLabel() !== 'PDF' ? ' · '.$r->lengthLabel() : '' }}{{ $r->summary ? ' · '.\Illuminate\Support\Str::limit($r->summary, 70) : '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12.5px;">{{ $r->topic ?? '—' }}</td>
                    <td>{!! $r->shares_count ? '<strong>'.$r->shares_count.'</strong> <span style="font-size:12px;color:var(--ink-500);">times</span>' : '<span style="color:var(--ink-300);">Not yet</span>' !!}</td>
                    <td><span class="mw-dot {{ $r->is_published ? 'ok' : '' }}">{{ $r->is_published ? 'Published' : 'Hidden' }}</span></td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        <a href="{{ route('resources.show', $r) }}" class="btn ghost sm">View</a>
                        <a href="{{ route('admin.resources.edit', $r) }}" class="btn ghost sm">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.5C10 5 7 4.5 4 5v13c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5V5c-3-.5-6 0-8 1.5z"/><path d="M12 6.5v13"/></svg>
                            <div class="big">{{ $q || $topic ? 'Nothing matches' : 'The library is empty' }}</div>
                            <div>{{ $q || $topic ? 'Try another search or topic.' : 'Add the first article, worksheet, audio or video for clients.' }}</div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('partials.pager', ['paginator' => $resources])
@endsection
