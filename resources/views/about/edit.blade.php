@extends('layouts.app')
@section('title', 'About you')
@section('content')
{{-- "About you" page (2026-10-09, roadmap 63). See AboutYouController. Same questions as the
     intake wizard (intake/_about), shown all at once; everything is optional. --}}
<div style="max-width:660px;margin:0 auto;">
    <div style="margin-bottom:16px;">
        <h1 style="font-size:21px;font-weight:800;color:var(--ink-900);margin:0 0 6px;">Tell your therapist a bit about you</h1>
        <p style="font-size:13px;color:var(--ink-500);line-height:1.6;margin:0;">
            Answer as much or as little as you like — every question is optional. Only your therapist and the Mindwell care
            team see this. If you need help right now, <a href="{{ route('urgent-help') }}" style="color:var(--teal-600, #2f6f66);font-weight:700;">get urgent help</a>.
        </p>
    </div>

    @if ($errors->any())
        <div class="mw-alert danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('about.update') }}">
        @csrf
        @method('PUT')
        @include('intake._about', ['mode' => 'page', 'sectionsOn' => $sectionsOn, 'detail' => $detail, 'languageDefault' => $languageDefault])

        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:4px;">
            <button type="submit" class="btn coral" style="border-radius:999px;">Save</button>
            @unless ($detail && $detail->hasAnswers())
                <button type="submit" form="mw-about-dismiss" class="mw-about-skip">Not now</button>
            @endunless
        </div>
    </form>
    <form id="mw-about-dismiss" method="POST" action="{{ route('about.dismiss') }}">@csrf</form>
</div>
@endsection
