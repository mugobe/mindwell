@extends('layouts.app')
@section('title', 'Application')
@section('content')
{{-- Admin review of one therapist application (2026-11-03). Answers on the left, checklist
     and decision on the right. See Admin\TherapistApplicationController. --}}
@include('apply._style')
@php
    $S = \App\Models\TherapistApplication::class;
    $flags = $app->flaggedDeclarations();
    $open = in_array($app->status, [$S::SUBMITTED, $S::CHANGES], true);
    $outstanding = $app->checklistOutstanding();
@endphp
<style>
    .rv-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
    .rv-side { position: sticky; top: 84px; display: grid; gap: 14px; }
    .rv-side .ap-card { margin: 0; }
    .rv-check { display: flex; gap: 10px; align-items: flex-start; padding: 8px 0; font-size: 13px; color: var(--ink-900); border-top: 1px solid var(--mw-line); }
    .rv-check:first-of-type { border-top: 0; }
    .rv-check input { margin-top: 2px; width: 16px; height: 16px; accent-color: #00A87C; flex: 0 0 16px; }
    .rv-check small { display: block; color: var(--ink-500); font-size: 11.5px; }
    .rv-dec label.opt { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: 10px; box-shadow: 0 0 0 1px var(--mw-line); margin-bottom: 8px; cursor: pointer; font-size: 13px; }
    .rv-dec label.opt:has(input:checked) { box-shadow: 0 0 0 1.5px #034C8F; background: #F1F6FC; }
    .rv-dec label.opt b { display: block; color: var(--ink-900); }
    .rv-dec label.opt span.s { color: var(--ink-500); font-size: 12px; }
    @media (max-width: 1100px) { .rv-grid { grid-template-columns: 1fr; } .rv-side { position: static; } }
</style>

<div class="mw-page-head">
    <div style="display:flex;align-items:center;gap:14px;">
        @include('partials.avatar', ['name' => $app->user?->name ?? 'Applicant', 'size' => 46])
        <div>
            <h1 style="margin:0;">{{ $app->user?->name ?? 'Deleted user' }}</h1>
            <div class="sub">{{ $app->professionLabel() ?? 'Profession not given' }} · {{ $app->user?->email }} · {{ \App\Support\UgandaPhone::display($app->user?->phone) }}</div>
        </div>
    </div>
    <div class="actions" style="align-items:center;">
        <span class="mw-chip {{ $app->statusTone() }}">{{ $app->statusLabel() }}</span>
        <a href="{{ route('admin.applications.index', ['tab' => $app->status]) }}" class="btn ghost sm">All applications</a>
    </div>
</div>

@if ($errors->any())
    <div class="mw-alert danger">{{ $errors->first() }}</div>
@endif

<div class="rv-grid">
    <div>
        @if ($flags)
            <div class="ap-card" style="border-left:4px solid #B7791F;">
                <h2>{{ count($flags) }} fitness to practise {{ count($flags) === 1 ? 'answer needs' : 'answers need' }} a look</h2>
                @foreach ($flags as $f)
                    <div style="margin-top:12px;font-size:13.5px;"><b>{{ $f['question'] }}</b><div style="white-space:pre-line;margin-top:4px;color:var(--ink-700);">{{ $f['details'] ?: 'No details given.' }}</div></div>
                @endforeach
            </div>
        @endif
        @if ($missing)
            <div class="ap-missing"><b>Incomplete.</b> This application can't be approved until these are added:
                <ul>@foreach (collect($missing)->flatten() as $m)<li>{{ $m }}</li>@endforeach</ul>
            </div>
        @endif

        @include('apply._summary', ['mwAdmin' => true])

        @if ($history->isNotEmpty())
            <div class="ap-card">
                <h2>History</h2>
                <ul style="list-style:none;margin:10px 0 0;padding:0;font-size:13px;">
                    @foreach ($history as $h)
                        <li style="padding:7px 0;border-top:1px solid var(--mw-line);display:flex;justify-content:space-between;gap:12px;">
                            <span>{{ $h->description }} @if (! empty($h->meta['note']))<span style="color:var(--ink-500);">“{{ \Illuminate\Support\Str::limit($h->meta['note'], 120) }}”</span>@endif</span>
                            <span style="color:var(--ink-500);white-space:nowrap;">{{ $h->user?->name ?? 'Applicant' }} · {{ $h->created_at->format('j M, g:i A') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="rv-side">
        <form method="POST" action="{{ route('admin.applications.checklist', $app) }}" class="ap-card">
            @csrf
            <h2>Verification checklist</h2>
            <p class="hint" style="margin-bottom:8px;">Items marked <b>required</b> must be ticked before you can approve.</p>
            @foreach (config('onboarding.checklist') as $key => $item)
                @php $c = $app->checklist[$key] ?? []; @endphp
                <label class="rv-check">
                    <input type="checkbox" name="items[]" value="{{ $key }}" @checked($c['done'] ?? false) @disabled(! $open)>
                    <span>{{ $item['label'] }} @if ($item['required'])<span class="mw-chip grey" style="padding:1px 7px;">required</span>@endif
                        @if (! empty($c['done']))<small>Ticked by {{ $c['by'] ?? 'an admin' }}, {{ \Illuminate\Support\Carbon::parse($c['at'])->format('j M, g:i A') }}</small>@endif
                    </span>
                </label>
            @endforeach
            @if ($open)
                <div style="margin-top:10px;text-align:right;"><button type="submit" class="btn ghost sm">Save checklist</button></div>
            @endif
        </form>

        @if ($open)
            <form method="POST" action="{{ route('admin.applications.decide', $app) }}" class="ap-card rv-dec">
                @csrf
                <h2>Decision</h2>
                <p class="hint" style="margin-bottom:10px;">The applicant is emailed straight away.</p>
                <label class="opt"><input type="radio" name="decision" value="approve" @checked(old('decision') === 'approve') @disabled($outstanding || $missing)>
                    <span><b>Approve</b><span class="s">{{ $outstanding ? 'Tick the required checklist items first.' : ($missing ? 'Incomplete application.' : 'Their profile goes live and their portal opens.') }}</span></span></label>
                <label class="opt"><input type="radio" name="decision" value="changes" @checked(old('decision', $outstanding || $missing ? 'changes' : null) === 'changes')>
                    <span><b>Ask for changes</b><span class="s">Sends it back with your note. They edit and resend.</span></span></label>
                <label class="opt"><input type="radio" name="decision" value="decline" @checked(old('decision') === 'decline')>
                    <span><b>Decline</b><span class="s">Closes the application.</span></span></label>
                <label class="mw-label" for="note" style="margin-top:6px;">Note to the applicant <span class="ap-opt">(needed to ask for changes or decline)</span></label>
                <textarea class="mw-field" id="note" name="note" rows="4" maxlength="3000" placeholder="e.g. Please upload a clearer copy of your licence certificate; the number isn't readable.">{{ old('note') }}</textarea>
                <div style="margin-top:12px;text-align:right;"><button type="submit" class="btn primary sm">Send decision</button></div>
            </form>
        @else
            <div class="ap-card">
                <h2>Decision</h2>
                <p style="font-size:13.5px;margin:6px 0 0;">
                    @if ($app->status === $S::DRAFT)
                        Not sent yet. {{ $app->user?->name }} is still filling it in.
                    @else
                        <span class="mw-chip {{ $app->statusTone() }}">{{ $app->statusLabel() }}</span>
                        by {{ $app->decider?->name ?? 'an admin' }} on {{ $app->decided_at?->format('j M Y') }}.
                    @endif
                </p>
                @if ($app->decision_note)<div style="margin-top:10px;font-size:13px;color:var(--ink-700);white-space:pre-line;">{{ $app->decision_note }}</div>@endif
                @if ($app->status === $S::APPROVED && $app->therapistProfile)
                    <div style="margin-top:12px;"><a href="{{ route('admin.therapists.index') }}#verified" class="btn ghost sm">See in Therapists</a></div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
