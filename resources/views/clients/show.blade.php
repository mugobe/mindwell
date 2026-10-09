@extends('layouts.app')
@section('title', 'Client file')
@section('content')
{{-- Added 2026-10-06 (roadmap 71) — the client file from Dr. Ntabade's THERAPIST DRAFT:
     bio data, intake & assessment scores, therapy summary, session history. Shared by
     therapists (their own clients) and admins (everyone; viewing is audit-logged). --}}
@php
    $S = \App\Support\Screeners::class;
    $u = $summary['user'];
    $first = $timeline->first();
    $latest = $timeline->last();
    $bandPill = fn (?string $band) => match ($band) { 'Minimal', 'Mild' => 'success', 'Moderate' => 'warn', null => '', default => 'danger' };
    $outcomes = \App\Models\TherapySession::CLOSURE_OUTCOMES;
    $statusPill = fn (string $s) => match ($s) {
        'completed' => ['success', 'Completed'],
        'scheduled' => ['teal', 'Scheduled'],
        'pending_approval' => ['warn', 'Awaiting approval'],
        'pending_payment' => ['warn', 'Awaiting payment'],
        'routing' => ['warn', 'In the lobby'],
        'cancelled' => ['danger', 'Cancelled'],
        default => ['', ucfirst($s)],
    };
@endphp

{{-- 2026-10-08 redesign (round 3): page kit head with the client's bubble. --}}
<div class="mw-page-head">
    <div style="display:flex;align-items:center;gap:14px;">
        @include('partials.avatar', ['name' => $summary['name'], 'size' => 52])
        <div>
            <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);letter-spacing:.03em;">{{ $summary['label'] ?? 'Client' }}</div>
            <h1 style="margin-top:1px;">{{ $summary['name'] }}</h1>
            <div class="sub" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:4px;">
                <span class="pill {{ $summary['status'][0] }}">{{ $summary['status'][1] }}</span>
                @if ($summary['concern'])
                    <span>{{ \Illuminate\Support\Str::limit($summary['concern'], 80) }}</span>
                @endif
            </div>
        </div>
    </div>
    <div class="actions">
        <a href="{{ route($backRoute) }}" class="btn ghost sm">{{ $viewer === 'admin' ? 'All clients' : 'My clients' }}</a>
        <a href="{{ route($progressRoute, $summary['profile']) }}" class="btn ghost sm">Progress charts</a>
        @if ($viewer === 'therapist')
            <a href="{{ route('messages.start', $summary['profile']->id) }}" class="btn primary sm">Message</a>
        @endif
    </div>
</div>

@foreach ($openFlags as $flag)
    <div class="mw-alert danger" style="margin-bottom:12px;">
        <strong>Crisis flag ({{ $flag->status }}) · {{ $flag->created_at->format('M j, g:i A') }}:</strong> {{ $flag->reason }}
    </div>
@endforeach

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;margin-bottom:16px;">
    {{-- Bio data --}}
    <div class="card">
        <div class="section-title">Bio data</div>
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            @php
                $bio = [
                    'Age' => $summary['age'] !== null ? $summary['age'].' ('.$u?->date_of_birth?->format('j M Y').')' : null,
                    'Gender' => $summary['gender'],
                    'Phone' => $u?->phone ? \App\Support\UgandaPhone::display($u->phone) : null,
                    'Email' => $u?->email,
                    'Preferred language' => $u?->preferred_language,
                ];
            @endphp
            @foreach ($bio as $k => $v)
                <tr><td style="padding:5px 0;color:var(--ink-500);width:42%;">{{ $k }}</td><td style="padding:5px 0;color:var(--ink-900);">{{ $v ?: '—' }}</td></tr>
            @endforeach
            <tr>
                <td style="padding:5px 0;color:var(--ink-500);vertical-align:top;">Emergency contact</td>
                <td style="padding:5px 0;color:var(--ink-900);">
                    @if ($u?->emergency_contact_name)
                        {{ $u->emergency_contact_name }}{{ $u->emergency_contact_relationship ? ' ('.$u->emergency_contact_relationship.')' : '' }}
                        @if ($u->emergency_contact_phone)<div>{{ \App\Support\UgandaPhone::display($u->emergency_contact_phone) }}</div>@endif
                    @else
                        —
                    @endif
                </td>
            </tr>
        </table>
        @if (! $u?->date_of_birth || ! $u?->emergency_contact_name)
            <div style="font-size:11px;color:var(--ink-300);margin-top:8px;">Missing details are added by the client in Settings → My details.</div>
        @endif
    </div>

    {{-- Assessments --}}
    <div class="card">
        <div class="section-title">Assessments</div>
        @if ($latest)
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                @foreach ([['phq9', 'PHQ-9'], ['gad7', 'GAD-7']] as [$scale, $title])
                    @php $band = $S::band($scale, $latest[$scale]); @endphp
                    <div>
                        <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);">{{ $title }}</div>
                        <div style="font-size:22px;font-weight:800;color:var(--ink-900);">{{ $latest[$scale] }}</div>
                        <span class="pill {{ $bandPill($band) }}">{{ $band }}</span>
                        @if ($first && $timeline->count() > 1)
                            @php $d = $latest[$scale] - $first[$scale]; @endphp
                            <div style="font-size:11.5px;color:var(--ink-500);margin-top:4px;">{{ $d === 0 ? '±0' : ($d > 0 ? '+'.$d : $d) }} since intake ({{ $first[$scale] }})</div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div style="font-size:12px;color:var(--ink-500);margin-top:10px;">
                Last {{ $latest['source'] === 'intake' ? 'intake' : 'check-in' }}: {{ $latest['at']->format('j M Y') }}
                · {{ $timeline->where('source', 'check-in')->count() }} check-in(s)
                @if ($checkInStatus['eligible'])
                    · next due {{ $checkInStatus['due_at']->format('j M') }}{{ $checkInStatus['due'] ? ' (due now)' : '' }}
                @endif
            </div>
        @else
            <p style="font-size:12.5px;color:var(--ink-500);margin:0;">No intake assessment yet.</p>
        @endif
        @if ($intake)
            <div style="font-size:12px;color:var(--ink-500);margin-top:8px;">Intake {{ $intake->created_at?->format('j M Y') }} · risk level: {{ ucfirst($intake->risk_level ?? '—') }}</div>
        @endif
    </div>

    {{-- Therapy summary --}}
    <div class="card">
        <div class="section-title">Therapy summary</div>
        @if ($latestCompleted)
            @foreach (['presenting_concerns' => 'Presenting concerns', 'goals_progress' => 'Progress toward goals', 'homework' => 'Homework given', 'plan_notes' => 'Plan / next steps'] as $f => $label)
                @if ($latestCompleted->{$f})
                    <div style="margin-bottom:8px;">
                        <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);">{{ $label }}</div>
                        <div style="font-size:13px;color:var(--ink-900);white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($latestCompleted->{$f}, 220) }}</div>
                    </div>
                @endif
            @endforeach
            <div style="font-size:11px;color:var(--ink-300);">From the last closed session ({{ $latestCompleted->closed_at?->format('j M Y') }}).</div>
        @else
            <p style="font-size:12.5px;color:var(--ink-500);margin:0;">Nothing yet — this fills in once a session is closed with notes.</p>
        @endif
        @if ($sharedNotesCount > 0)
            <div style="font-size:12px;color:var(--ink-500);margin-top:8px;">{{ $sharedNotesCount }} journal note(s) shared by the client — see them when you close a session.</div>
        @endif
    </div>
</div>

{{-- Richer intake (2026-10-09, roadmap 63): what the client told us in "About you". --}}
@if (\App\Models\ClientIntakeDetail::tableReady())
    <div class="card" style="margin-bottom:16px;">
        <div class="section-title">About them, in their words</div>
        @include('partials.about-client', ['profileId' => $summary['profile']->id])
    </div>
@endif

{{-- Session history --}}
<div class="card" style="padding:0;">
    <div class="section-title" style="padding:16px 16px 0;">Session history</div>
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">#</th>
                <th>Date</th>
                <th>Therapist</th>
                <th>Status</th>
                <th>Outcome</th>
                <th>Summary</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $s)
                @php
                    $mine = $viewer === 'admin' || $s->therapist_profile_id === $therapistProfileId;
                    [$pc, $pl] = $statusPill($s->status);
                @endphp
                <tr>
                    <td style="padding-left:16px;color:var(--ink-500);">{{ $s->session_number ?? '' }}</td>
                    <td style="white-space:nowrap;font-size:12.5px;">{{ ($s->scheduled_at ?? $s->created_at)->format('j M Y, g:i A') }}</td>
                    <td style="font-size:12.5px;">{{ $mine ? ($s->therapistProfile?->user?->name ?? '—') : 'Another therapist' }}</td>
                    <td><span class="pill {{ $pc }}">{{ $pl }}</span>@if ($s->isInPerson())<div style="font-size:11px;color:var(--ink-300);margin-top:2px;">In person</div>@endif</td>
                    <td style="font-size:12.5px;">{{ $s->closure_outcome ? ($outcomes[$s->closure_outcome] ?? $s->closure_outcome) : '—' }}</td>
                    <td style="font-size:12px;color:var(--ink-700);max-width:260px;">{{ $mine && $s->session_summary ? \Illuminate\Support\Str::limit($s->session_summary, 90) : '—' }}</td>
                    <td style="padding-right:16px;text-align:right;white-space:nowrap;">
                        @if ($s->status === 'completed' && $mine)
                            <a href="{{ $viewer === 'admin' ? route('admin.closed-sessions.show', $s) : route('therapist.reports.show', $s) }}" class="btn ghost sm">Notes</a>
                        @elseif ($s->status === 'scheduled' && $viewer === 'therapist' && $mine && $s->isInPerson())
                            <span class="pill success">In person</span>
                        @elseif ($s->status === 'scheduled' && $viewer === 'therapist' && $mine)
                            <a href="{{ route('sessions.room', $s) }}" class="btn coral sm">Join</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="padding:16px;color:var(--ink-500);">No sessions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    Clinical notes are only visible to authorised staff. {{ $viewer === 'therapist' ? 'Notes from sessions with other therapists are not shown here.' : 'This view is recorded in the audit log.' }}
</p>
@endsection
