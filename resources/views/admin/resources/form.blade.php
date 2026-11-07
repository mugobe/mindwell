@extends('layouts.app')
@section('title', $resource->exists ? 'Edit resource' : 'Add a resource')
@section('content')
{{-- Add / edit a library resource (2026-11-07). The fields shown depend on the kind:
     article = text written here; PDF/worksheet or audio = a file (20 MB); video or web link = an address. --}}
@php($k = old('kind', $resource->kind))

<div class="mw-page-head">
    <div>
        <h1>{{ $resource->exists ? 'Edit resource' : 'Add a resource' }}</h1>
        <div class="sub">Clients see published resources in their library. Therapists can send them to a client.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.resources.index') }}" class="btn ghost sm">Back to the library</a>
    </div>
</div>

<form method="POST" action="{{ $resource->exists ? route('admin.resources.update', $resource) : route('admin.resources.store') }}" enctype="multipart/form-data" class="card" style="max-width:760px;padding:22px;">
    @csrf
    @if ($resource->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="mw-alert danger" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <label class="mw-label" for="title">Title</label>
    <input class="mw-field" id="title" name="title" value="{{ old('title', $resource->title) }}" maxlength="160" required>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-top:14px;">
        <div>
            <label class="mw-label" for="kind">Kind</label>
            <select class="mw-field" id="kind" name="kind">
                @foreach (\App\Models\Resource::KINDS as $v => $l)
                    <option value="{{ $v }}" @selected($k === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mw-label" for="topic">Topic</label>
            <input class="mw-field" id="topic" name="topic" value="{{ old('topic', $resource->topic) }}" list="mw-topics" maxlength="60" placeholder="For example: Sleep">
            <datalist id="mw-topics">@foreach (\App\Models\Resource::TOPICS as $t)<option value="{{ $t }}">@endforeach</datalist>
        </div>
        <div>
            <label class="mw-label" for="minutes">Minutes to read, watch or listen</label>
            <input class="mw-field" type="number" id="minutes" name="minutes" min="1" max="600" value="{{ old('minutes', $resource->minutes) }}" placeholder="Optional">
        </div>
    </div>

    <label class="mw-label" for="summary" style="margin-top:14px;">Short summary</label>
    <input class="mw-field" id="summary" name="summary" value="{{ old('summary', $resource->summary) }}" maxlength="300" placeholder="One sentence clients see in the list">

    <div data-kind="article" style="margin-top:14px;">
        <label class="mw-label" for="body">Article text</label>
        <textarea class="mw-field" id="body" name="body" rows="14" placeholder="Write the article. Leave an empty line between paragraphs.">{{ old('body', $resource->body) }}</textarea>
    </div>

    <div data-kind="pdf audio" style="margin-top:14px;">
        <label class="mw-label" for="file">File (PDF, or audio: MP3, M4A, WAV, OGG · up to 20 MB)</label>
        @if ($resource->file_path)
            <div style="font-size:12.5px;color:var(--ink-500);margin-bottom:6px;">Current file: <a href="{{ route('resources.file', $resource) }}" target="_blank" rel="noopener">{{ $resource->file_name }}</a>. Choose a new one only to replace it.</div>
        @endif
        <input class="mw-field" type="file" id="file" name="file" accept="application/pdf,audio/*">
    </div>

    <div data-kind="video link" style="margin-top:14px;">
        <label class="mw-label" for="url">Address (YouTube or any web page)</label>
        <input class="mw-field" type="url" id="url" name="url" value="{{ old('url', $resource->url) }}" maxlength="500" placeholder="https://">
        <div style="font-size:11.5px;color:var(--ink-500);margin-top:4px;">YouTube videos play inside Mindwell. Other links open in a new tab.</div>
    </div>

    <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--ink-700);margin-top:18px;">
        <input type="hidden" name="is_published" value="0">
        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $resource->is_published))>
        Published (clients can see it)
    </label>

    <div style="display:flex;gap:10px;align-items:center;margin-top:20px;flex-wrap:wrap;">
        <button type="submit" class="btn primary">{{ $resource->exists ? 'Save' : 'Add to the library' }}</button>
        <a href="{{ route('admin.resources.index') }}" class="btn ghost">Cancel</a>
    </div>
</form>

@if ($resource->exists)
    <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}" style="max-width:760px;margin-top:12px;text-align:right;" onsubmit="return confirm('Remove this resource from the library? Clients it was sent to will no longer see it.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn ghost sm" style="color:#B3261E;">Remove from the library</button>
    </form>
@endif

<script>
(function () {
    var sel = document.getElementById('kind');
    function sync() {
        document.querySelectorAll('[data-kind]').forEach(function (el) {
            el.hidden = el.dataset.kind.split(' ').indexOf(sel.value) === -1;
        });
    }
    sel.addEventListener('change', sync); sync();
})();
</script>
@endsection
