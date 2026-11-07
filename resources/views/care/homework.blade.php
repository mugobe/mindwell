@extends('layouts.app')
@section('title', $homework->title)
@section('content')
{{-- One piece of homework (2026-11-07, roadmap 36): what to do, the worksheet if any, and the
     client's answer (words and/or a photo or PDF, 10 MB). Sending it marks it done. --}}
<div style="max-width:720px;">
    <a href="{{ route('care.index') }}" class="btn ghost sm" style="border-radius:999px;margin-bottom:14px;">&larr; My therapy</a>

    <h1 style="font-size:24px;font-weight:800;letter-spacing:-.02em;color:var(--ink-900);margin:0;">{{ $homework->title }}</h1>
    <div style="font-size:13px;color:var(--ink-500);margin-top:4px;">
        From {{ $homework->therapistProfile?->user?->name ?? 'your therapist' }} · {{ $homework->created_at->format('j M') }}
        @if ($homework->due_on) · <span style="{{ $homework->isOverdue() ? 'color:#B3261E;font-weight:700;' : '' }}">Due {{ $homework->due_on->format('l j M') }}</span>@endif
    </div>

    @if ($homework->instructions)
        <div class="card" style="margin-top:16px;padding:20px;">
            <div style="font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--mw-green, #00A87C);margin-bottom:8px;">What to do</div>
            <div style="font-size:15px;line-height:1.7;color:var(--ink-900);white-space:pre-line;">{{ $homework->instructions }}</div>
        </div>
    @endif

    @if ($homework->resource && $homework->resource->is_published)
        <a href="{{ route('resources.show', $homework->resource) }}" class="card mwc-lift" style="display:flex;gap:12px;align-items:center;text-decoration:none;margin-top:12px;">
            @include('resources._icon', ['kind' => $homework->resource->kind])
            <span style="flex:1;min-width:0;"><b style="display:block;font-size:14px;color:var(--ink-900);">{{ $homework->resource->title }}</b><span style="font-size:12.5px;color:var(--ink-500);">{{ $homework->resource->kindLabel() }} to use for this</span></span>
            <span style="font-size:13px;font-weight:700;color:var(--mw-navy, #034C8F);">Open</span>
        </a>
    @endif

    <form method="POST" action="{{ route('care.homework.reply', $homework) }}" enctype="multipart/form-data" class="card" style="margin-top:16px;padding:20px;">
        @csrf
        <h2 class="mwc-h2" style="margin:0 0 4px;">{{ $homework->isDone() ? 'Your answer' : 'Your answer' }}</h2>
        <div class="mwc-sub" style="margin-bottom:12px;">
            @if ($homework->isDone())
                Sent on {{ $homework->done_at->format('j M \a\t g:i A') }}{{ $homework->reviewed_at ? '. Your therapist has looked at it.' : '. Your therapist will look at it.' }} You can still change it.
            @else
                Write how it went, or attach a photo of your worksheet. Only your therapist sees it.
            @endif
        </div>
        @if ($errors->any())<div class="mw-alert danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>@endif
        <textarea class="mw-field" name="reply" rows="6" maxlength="10000" placeholder="What did you try? How did it go?">{{ old('reply', $homework->reply) }}</textarea>
        @if ($homework->reply_file_path)
            <div style="font-size:13px;margin-top:10px;">Attached: <a href="{{ $homework->replyFileUrl() }}" target="_blank" rel="noopener">{{ $homework->reply_file_name }}</a></div>
        @endif
        <label class="mw-label" for="hw-file" style="margin-top:12px;">{{ $homework->reply_file_path ? 'Replace the file (optional)' : 'Attach a photo or PDF (optional, up to 10 MB)' }}</label>
        <input class="mw-field" type="file" id="hw-file" name="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf">
        <button type="submit" class="btn coral" style="border-radius:999px;margin-top:14px;padding-left:26px;padding-right:26px;">{{ $homework->isDone() ? 'Update my answer' : 'Send to my therapist' }}</button>
    </form>
</div>
@endsection
