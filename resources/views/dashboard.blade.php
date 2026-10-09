@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

@if (auth()->user()->role === 'admin')
    @include('admin._overview')

@elseif (auth()->user()->role === 'therapist')
    @php $profile = auth()->user()->therapistProfile; @endphp

    @if (! $profile || ! $profile->credentials_verified)
        <div class="card" style="text-align:center;padding:48px 24px;">
            <div style="font-size:32px;margin-bottom:12px;">⏳</div>
            <h1 style="font-size:19px;font-weight:800;margin-bottom:8px;color:var(--ink-900);">
                Your application is under review
            </h1>
            <p style="font-size:13.5px;color:var(--ink-500);max-width:380px;margin:0 auto 14px;">
                Our credentialing team is verifying your license
                @if ($profile) ({{ $profile->license_number }}, {{ $profile->license_state }}) @endif
                — you'll get access to your therapist portal as soon as you're approved.
            </p>
            <span class="pill warn">Pending review</span>
        </div>
    @else
        {{-- Therapist dashboard (rebuilt 2026-10-06, roadmap 72 — Dr. Ntabade's THERAPIST
             DRAFT: today's overview, today's sessions with Join, quick actions; 2026-10-08
             redesign: page kit, "Needs your attention" queue; quick actions live in the sidebar).
             Numbers come from App\Services\TherapistOverview. --}}
        @php
            $mwOverview = new \App\Services\TherapistOverview($profile->id, auth()->user());
            $mwCounts = $mwOverview->counts();
            $mwToday = $mwOverview->today();
            $mwSoon = $mwOverview->upcoming(7)->reject(fn ($s) => $s->scheduled_at->isToday())->take(5);
            $mwHour = (int) now()->format('G');
            $mwGreeting = $mwHour < 12 ? 'Good morning' : ($mwHour < 17 ? 'Good afternoon' : 'Good evening');
            $mwFirstName = trim(explode(' ', auth()->user()->name)[0] ?? '');
        @endphp
        @php
            // 2026-10-08 redesign (round 3): same layout as the admin Overview — today first,
            // then what needs you, then the week ahead.
            $mwNextUp = $mwToday->first(fn ($s) => $s->status === 'scheduled' && $s->plannedEndAt()?->isFuture());
            $mwIcons = [
                'close' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 14l2 2 4-4"/>',
                'msg' => '<path d="M4 5h16v11H8l-4 4z"/>',
                'pulse' => '<path d="M3 12h4l2.5-6 4 12 2.5-6H21"/>',
                'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/>',
                'missed' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
                'wait' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
                'repeat' => '<path d="M4 8h12l-3-3M20 16H8l3 3"/>',
            ];
            $mwQueue = array_values(array_filter([
                $mwCounts['to_close'] ? ['icon' => 'close', 'tone' => 'warm', 'title' => 'Sessions to close out', 'sub' => 'Write the report so the client file is complete', 'count' => $mwCounts['to_close'], 'href' => route('therapist.sessions.index'), 'cta' => 'Close out'] : null,
                $mwCounts['missed'] ? ['icon' => 'missed', 'tone' => 'urgent', 'title' => 'Missed sessions', 'sub' => 'The client never joined the room', 'count' => $mwCounts['missed'], 'href' => route('therapist.sessions.index'), 'cta' => 'Review'] : null,
                $mwCounts['unread_messages'] ? ['icon' => 'msg', 'tone' => 'teal', 'title' => 'Unread messages', 'sub' => 'From your clients', 'count' => $mwCounts['unread_messages'], 'href' => route('messages.index'), 'cta' => 'Open'] : null,
                $mwCounts['check_ins_due'] ? ['icon' => 'pulse', 'tone' => 'teal', 'title' => 'Check-ins due', 'sub' => 'Clients due their two-weekly PHQ-9 / GAD-7', 'count' => $mwCounts['check_ins_due'], 'href' => route('therapist.clients.index'), 'cta' => 'See clients'] : null,
                $mwCounts['new_clients'] ? ['icon' => 'user', 'tone' => 'teal', 'title' => 'New clients', 'sub' => 'First session in the last 14 days — read their intake', 'count' => $mwCounts['new_clients'], 'href' => route('therapist.clients.index'), 'cta' => 'See clients'] : null,
                $mwCounts['pending_approval'] ? ['icon' => 'wait', 'tone' => 'teal', 'title' => 'Waiting for admin approval', 'sub' => 'Bookings and follow-ups the team hasn\'t confirmed yet', 'count' => $mwCounts['pending_approval'], 'href' => route('therapist.sessions.index'), 'cta' => 'View'] : null,
            ]));
        @endphp

        <div class="mw-page-head">
            <div>
                <h1>{{ $mwGreeting }}, {{ $mwFirstName }}</h1>
                <div class="sub">
                    {{ now()->format('l, j F') }} ·
                    @if ($mwCounts['today'])
                        {{ $mwCounts['today'] }} {{ $mwCounts['today'] === 1 ? 'session' : 'sessions' }} today{{ $mwNextUp ? ', next at '.$mwNextUp->scheduled_at->format('g:i A') : '' }}
                    @else
                        no sessions today
                    @endif
                </div>
            </div>
            <div class="actions">
                <a href="{{ route('therapist.availability.index') }}" class="btn ghost sm">Availability</a>
                <a href="{{ route('therapist.calendar') }}" class="btn primary sm">Open calendar</a>
            </div>
        </div>

        <div class="mw-kpis">
            <a class="mw-kpi" href="{{ route('therapist.calendar', ['view' => 'day']) }}">
                <div class="k">Sessions today</div>
                <div class="v">{{ $mwCounts['today'] }}</div>
                <div class="d">{{ $mwCounts['upcoming'] }} booked in total ahead</div>
            </a>
            <a class="mw-kpi" href="{{ route('therapist.clients.index') }}">
                <div class="k">Active clients</div>
                <div class="v">{{ $mwCounts['active_clients'] }}</div>
                <div class="d">{{ $mwCounts['new_clients'] ? $mwCounts['new_clients'].' new in the last 14 days' : 'Seen or booked in the last 60 days' }}</div>
            </a>
            <a class="mw-kpi" href="{{ route('therapist.sessions.index') }}">
                <div class="k">To close out</div>
                <div class="v" style="{{ $mwCounts['to_close'] ? 'color:var(--mw-orange, #E8825E);' : '' }}">{{ $mwCounts['to_close'] }}</div>
                <div class="d">{{ $mwCounts['to_close'] ? 'Reports waiting for you' : 'All reports written' }}</div>
            </a>
            <a class="mw-kpi" href="{{ route('therapist.stats') }}">
                <div class="k">Sessions this month</div>
                <div class="v">{{ $mwCounts['completed_30'] }}</div>
                <div class="d">Completed in the last 30 days</div>
            </a>
        </div>

        <div class="mw-ov-grid" style="display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:16px;align-items:start;">
            <div class="card" style="padding:0;">
                <div class="mw-card-head">
                    <h2>Today · {{ $mwToday->count() }} {{ $mwToday->count() === 1 ? 'session' : 'sessions' }}</h2>
                    <a href="{{ route('therapist.calendar', ['view' => 'day']) }}">Day view →</a>
                </div>
                @if ($mwToday->isNotEmpty())
                    <ul class="mw-agenda">
                        @foreach ($mwToday as $mwS)
                            @php
                                $mwClient = $mwS->clientProfile?->user;
                                $mwKind = $mwS->routing_method === 'therapist_scheduled' ? 'Follow-up' : ($mwOverview->isFirstWith($mwS) ? 'First session' : 'Session');
                                $mwPast = $mwS->plannedEndAt()?->isPast();
                            @endphp
                            <li>
                                <div class="time {{ $mwPast ? 'past' : '' }}">{{ $mwS->scheduled_at->format('g:i A') }}</div>
                                @include('partials.avatar', ['name' => $mwClient?->name ?? 'Client', 'size' => 34])
                                <div style="min-width:0;">
                                    <a class="who" href="{{ route('therapist.clients.show', $mwS->client_profile_id) }}" style="text-decoration:none;">{{ $mwClient?->name ?? 'Client' }}</a>
                                    <div class="meta">
                                        @if ($mwKind === 'First session')<span style="color:var(--mw-orange, #E8825E);font-weight:700;">First session</span>@else{{ $mwKind }}@endif
                                        · {{ $mwS->duration_minutes ?: config('mindwell.session_duration_minutes') }} min · {{ $mwS->isInPerson() ? 'In person'.($mwS->venue ? ', '.$mwS->venue->name : '') : 'Video' }}
                                    </div>
                                </div>
                                <div style="white-space:nowrap;">
                                    @if ($mwS->status === 'completed')
                                        <span class="mw-dot ok">Closed</span>
                                    @elseif ($mwS->isInPerson() || $mwPast)
                                        <a href="{{ route('therapist.sessions.close', $mwS) }}" class="btn {{ $mwPast ? 'primary' : 'ghost' }} sm">Close out</a>
                                    @else
                                        <a href="{{ route('sessions.room', $mwS) }}" class="btn coral sm">Join</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="mw-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg>
                        <div class="big">No sessions today</div>
                        <a href="{{ route('therapist.calendar') }}" style="color:var(--teal-600,#2f6f66);font-weight:700;">See your week</a>
                    </div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:16px;">
                <div class="card" style="padding:0;">
                    <div class="mw-card-head"><h2>Needs your attention</h2></div>
                    @if (count($mwQueue))
                        <ul class="mw-queue">
                            @foreach ($mwQueue as $mwItem)
                                <li>
                                    <span class="ic {{ $mwItem['tone'] }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $mwIcons[$mwItem['icon']] !!}</svg></span>
                                    <div style="min-width:0;flex:1;">
                                        <div class="t">{{ $mwItem['title'] }}</div>
                                        <div class="s">{{ $mwItem['sub'] }}</div>
                                    </div>
                                    <span class="num {{ $mwItem['tone'] === 'urgent' ? 'urgent' : '' }}">{{ $mwItem['count'] }}</span>
                                    <a href="{{ $mwItem['href'] }}" class="btn ghost sm" style="white-space:nowrap;">{{ $mwItem['cta'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                            <div class="big">You're all caught up</div>
                            No reports, messages or check-ins waiting.
                        </div>
                    @endif
                </div>

                <div class="card" style="padding:0;">
                    <div class="mw-card-head">
                        <h2>Coming up this week</h2>
                        <a href="{{ route('therapist.calendar', ['view' => 'week']) }}">Week view →</a>
                    </div>
                    @if ($mwSoon->isNotEmpty())
                        <ul class="mw-agenda">
                            @foreach ($mwSoon as $mwS)
                                <li style="grid-template-columns:84px auto minmax(0,1fr);">
                                    <div class="time" style="font-size:12px;line-height:1.25;white-space:nowrap;">{{ $mwS->scheduled_at->format('D j M') }}<br><span style="font-weight:600;color:var(--ink-500);">{{ $mwS->scheduled_at->format('g:i A') }}</span></div>
                                    @include('partials.avatar', ['name' => $mwS->clientProfile?->user?->name ?? 'Client', 'size' => 28])
                                    <div style="min-width:0;">
                                        <a class="who" href="{{ route('therapist.clients.show', $mwS->client_profile_id) }}" style="text-decoration:none;">{{ $mwS->clientProfile?->user?->name ?? 'Client' }}</a>
                                        <div class="meta">{{ $mwS->isInPerson() ? 'In person'.($mwS->venue ? ', '.$mwS->venue->name : '') : 'Video' }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mw-empty" style="padding:22px 16px;">Nothing else booked in the next 7 days.</div>
                    @endif
                </div>
            </div>
        </div>
        <style>@media (max-width: 1100px) { .mw-ov-grid { grid-template-columns: 1fr !important; } }</style>
    @endif
@else
    @php
        $clientProfile = auth()->user()->clientProfile;
        $latestIntake = $clientProfile?->intakeAssessments()->latest()->first();
        $latestSession = $clientProfile
            ? \App\Models\TherapySession::where('client_profile_id', $clientProfile->id)->latest()->first()
            : null;
        $lastCompletedSession = $clientProfile
            ? \App\Models\TherapySession::where('client_profile_id', $clientProfile->id)->where('status', 'completed')->with('therapistProfile.user')->latest()->first()
            : null;
        $inProgress = $latestSession && in_array($latestSession->status, ['pending_payment', 'routing', 'pending_approval', 'scheduled']);

        // Phase 0 (2026-10-05), from Dr. Ntabade's CLIENT DRAFT: next session card with
        // a Join button, and "How are you feeling today?" right on the dashboard.
        $mwNext = $clientProfile
            ? \App\Models\TherapySession::where('client_profile_id', $clientProfile->id)
                ->where('status', 'scheduled')
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '>=', now()->subMinutes((int) config('mindwell.session_duration_minutes', 70) + (int) config('mindwell.video_join_late_minutes', 30)))
                ->with('therapistProfile.user')
                ->orderBy('scheduled_at')
                ->first()
            : null;
        if ($mwNext && $latestSession && $latestSession->status === 'scheduled') {
            $inProgress = false; // the next-session card below covers it
        }
        $mwMoodToday = $clientProfile
            ? \App\Models\JournalEntry::where('client_profile_id', $clientProfile->id)
                ->where('kind', 'daily')->whereNotNull('mood')
                ->where('created_at', '>=', now()->startOfDay())
                ->latest()->value('mood')
            : null;
    @endphp

    {{-- Warmer, more colorful client dashboard (2026-09-27 design pass, flagship page —
         reference: Google Meet's home screen). Illustrations via partials.mw-illustration,
         pastel tones via the --mw-* variables defined in layouts/app.blade.php. Admin and
         therapist dashboards above are untouched. --}}
    <div style="background:#fff;border:1px solid var(--mw-teal-tint);border-radius:16px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <span style="flex-shrink:0;width:34px;height:34px;border-radius:50%;background:var(--mw-teal-tint);display:flex;align-items:center;justify-content:center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="var(--teal-600,#2f6f66)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px;"><path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z"/><path d="M9 12.5l2 2 4-4.5"/></svg>
        </span>
        <div>
            <div style="font-size:13px;font-weight:700;color:var(--ink-900);">Your privacy comes first</div>
            <div style="font-size:12px;color:var(--ink-500);">Sessions and reports are confidential — only you and your therapist ever see them.</div>
        </div>
    </div>

    {{-- 2026-10-06: nudge clients to add an emergency contact (shown in their client file). --}}
    @if (! auth()->user()->emergency_contact_name)
        <div class="card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 18px;border-left:4px solid var(--coral-500, #e8825e);">
            <div>
                <div style="font-size:13.5px;font-weight:700;color:var(--ink-900);">Add an emergency contact</div>
                <div style="font-size:12.5px;color:var(--ink-500);">Someone we can reach if we're ever worried about your safety. Only your care team sees it.</div>
            </div>
            <a href="{{ route('settings.details') }}" class="btn ghost sm" style="border-radius:999px;">Add my details</a>
        </div>
    @endif

    {{-- Richer intake (2026-10-09, roadmap 63): clients who did intake before the "About you"
         questions existed are asked once — until they fill it in or say "Not now". --}}
    @if ($latestIntake && $clientProfile && \App\Models\ClientIntakeDetail::tableReady() && \App\Support\IntakeQuestions::anyOn() && ! \App\Models\ClientIntakeDetail::for($clientProfile->id))
        <div class="card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 18px;border-left:4px solid var(--teal-600, #2f6f66);">
            <div>
                <div style="font-size:13.5px;font-weight:700;color:var(--ink-900);">Tell your therapist a bit about you</div>
                <div style="font-size:12.5px;color:var(--ink-500);">What brings you here and what you hope for — a couple of minutes, and every question is optional.</div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <form method="POST" action="{{ route('about.dismiss') }}">@csrf<button type="submit" style="background:none;border:0;font-size:12.5px;font-weight:700;color:var(--ink-500);cursor:pointer;">Not now</button></form>
                <a href="{{ route('about.edit') }}" class="btn primary sm" style="border-radius:999px;">Start</a>
            </div>
        </div>
    @endif

    @if ($mwNext)
        @php
            $mwJoinFrom = $mwNext->scheduled_at->copy()->subMinutes((int) config('mindwell.video_join_early_minutes', 10));
            $mwCanJoin = now()->gte($mwJoinFrom);
        @endphp
        <div class="card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;border-left:4px solid var(--teal-600, #2f6f66);">
            <div>
                <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;">Your next session</div>
                <div style="font-size:17px;font-weight:800;color:var(--ink-900);margin-top:4px;">{{ $mwNext->scheduled_at->format('l, j F') }} · {{ $mwNext->scheduled_at->format('g:i A') }}</div>
                <div style="font-size:13px;color:var(--ink-500);margin-top:2px;">
                    With {{ $mwNext->therapistProfile?->user?->name ?? 'your therapist' }} · {{ $mwNext->isInPerson() ? 'In person' : 'Video session' }} · {{ $mwNext->duration_minutes ?: config('mindwell.session_duration_minutes') }} minutes
                </div>
                @if ($mwNext->isInPerson())
                    <div style="font-size:12.5px;color:var(--ink-700);margin-top:4px;">{{ $mwNext->locationText() ?? 'Place to be confirmed by our team' }}</div>
                @endif
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            @if ($mwNext->isInPerson())
                <a href="{{ route('sessions.connected', $mwNext) }}" class="btn ghost" style="border-radius:999px;padding:10px 22px;">Where to go</a>
            @else
                <a href="{{ route('sessions.room', $mwNext) }}" class="btn {{ $mwCanJoin ? 'coral' : 'ghost' }}" style="border-radius:999px;padding:10px 22px;">
                    {{ $mwCanJoin ? 'Join session' : 'Session room' }}
                </a>
                @unless ($mwCanJoin)
                    <span style="font-size:11.5px;color:var(--ink-500);">You can join from {{ $mwJoinFrom->format('g:i A') }}{{ $mwJoinFrom->isToday() ? '' : ' on '.$mwJoinFrom->format('D j M') }}</span>
                @endunless
            @endif
            </div>
        </div>
    @endif

    @if ($latestIntake)
        <div class="card" style="margin-bottom:20px;padding:16px 18px;">
            @if ($mwMoodToday)
                <div style="font-size:13px;color:var(--ink-700);">
                    Today you said you're feeling <strong>{{ strtolower(\App\Models\JournalEntry::MOODS[$mwMoodToday] ?? '') }}</strong>.
                    <a href="{{ route('journal.index') }}" style="color:var(--teal-600, #2f6f66);font-weight:700;">Add a note in your journal →</a>
                </div>
            @else
                <form method="POST" action="{{ route('journal.store') }}" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    @csrf
                    <input type="hidden" name="return" value="dashboard">
                    <span style="font-size:14px;font-weight:800;color:var(--ink-900);margin-right:4px;">How are you feeling today?</span>
                    @foreach (\App\Models\JournalEntry::MOODS as $mwMoodValue => $mwMoodLabel)
                        <button type="submit" name="mood" value="{{ $mwMoodValue }}" class="btn ghost sm" style="border-radius:999px;">{{ $mwMoodLabel }}</button>
                    @endforeach
                </form>
            @endif
        </div>
    @endif

    {{-- Progress check-in invitation (added 2026-10-03) — see App\Models\CheckIn. --}}
    @php $mwCheckIn = \App\Models\CheckIn::statusFor($clientProfile); @endphp
    @if ($mwCheckIn['due'])
        <div style="background:var(--mw-coral-tint, #FCE1D3);border-radius:16px;padding:16px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <div style="font-size:14px;font-weight:800;color:var(--ink-900);">Time for your check-in</div>
                <div style="font-size:12.5px;color:var(--ink-700);">About 3 minutes. It helps you and your therapist see how things are changing.</div>
            </div>
            <a href="{{ route('progress.check-in') }}" class="btn coral" style="border-radius:999px;padding:9px 22px;">Start check-in</a>
        </div>
    @endif

    {{-- "How was your session?" (added 2026-10-04) — see App\Models\SessionRating. --}}
    @php $mwToRate = \App\Models\SessionRating::pendingFor($clientProfile?->id); @endphp
    @if ($mwToRate)
        <div style="background:#fff;border:1px solid var(--mw-gold, #F6E1A8);border-radius:16px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <div style="font-size:14px;font-weight:800;color:var(--ink-900);"><span style="color:#e8a33d;">★</span> How was your session with {{ $mwToRate->therapistProfile?->user?->name ?? 'your therapist' }}?</div>
                <div style="font-size:12.5px;color:var(--ink-500);">{{ $mwToRate->scheduled_at->format('l, M j') }} · takes a few seconds</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <a href="{{ route('sessions.rate', $mwToRate) }}" class="btn primary sm" style="border-radius:999px;padding-left:18px;padding-right:18px;">Rate it</a>
                <form method="POST" action="{{ route('sessions.rate.store', $mwToRate) }}">
                    @csrf
                    <input type="hidden" name="skip" value="1">
                    <button type="submit" class="btn ghost sm" style="border-radius:999px;">Not now</button>
                </form>
            </div>
        </div>
    @endif

    @if (! $latestIntake)
        <div class="card" style="text-align:center;padding:44px 24px;">
            @include('partials.mw-illustration', ['variant' => 'sprout'])
            <h1 style="font-size:19px;font-weight:800;margin:14px 0 8px;color:var(--ink-900);">
                Welcome, {{ auth()->user()->name }}
            </h1>
            <p style="font-size:13.5px;color:var(--ink-500);max-width:360px;margin:0 auto 18px;">
                Let's start with a quick assessment so we can match you with the right therapist.
            </p>
            <a href="{{ route('intake.show') }}" class="btn coral" style="display:inline-block;width:auto;border-radius:999px;padding:11px 26px;">Start intake assessment</a>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:20px;">
                <span style="font-size:11px;font-weight:600;color:var(--teal-600,#2f6f66);background:var(--mw-teal-tint);padding:5px 12px;border-radius:999px;">Private &amp; confidential</span>
                <span style="font-size:11px;font-weight:600;color:#8a5a2a;background:var(--mw-gold);padding:5px 12px;border-radius:999px;">Licensed therapists</span>
                <span style="font-size:11px;font-weight:600;color:#a14b3a;background:var(--mw-coral-tint);padding:5px 12px;border-radius:999px;">Book anytime</span>
            </div>
        </div>
    @elseif ($inProgress)
        <div class="card" style="text-align:center;padding:44px 24px;">
            @include('partials.mw-illustration', ['variant' => 'hourglass'])
            <h1 style="font-size:19px;font-weight:800;margin:14px 0 8px;color:var(--ink-900);">
                You have a session in progress
            </h1>
            <p style="font-size:13.5px;color:var(--ink-500);max-width:360px;margin:0 auto 18px;">
                @switch($latestSession->status)
                    @case('pending_payment') Pick up where you left off to confirm it. @break
                    @case('routing') You're in the lobby, waiting to be connected. @break
                    @case('pending_approval') Just waiting on our team to confirm it. @break
                    @case('scheduled') You're all matched — see the details. @break
                @endswitch
            </p>
            <a href="{{ route(match($latestSession->status) {
                'pending_payment' => 'sessions.checkout',
                'routing' => 'sessions.lobby',
                'pending_approval' => 'sessions.pending-approval',
                'scheduled' => 'sessions.connected',
            }, $latestSession) }}" class="btn coral" style="display:inline-block;width:auto;border-radius:999px;padding:11px 26px;">Continue</a>
        </div>
    @elseif ($lastCompletedSession)
        <div class="card" style="text-align:center;padding:40px 24px;">
            @include('partials.mw-illustration', ['variant' => 'again'])
            <h1 style="font-size:19px;font-weight:800;margin:14px 0 8px;color:var(--ink-900);">
                Welcome back, {{ auth()->user()->name }}
            </h1>
            <p style="font-size:13.5px;color:var(--ink-500);max-width:380px;margin:0 auto 18px;">
                Ready for another session with {{ $lastCompletedSession->therapistProfile?->user?->name ?? 'your therapist' }}?
            </p>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                @if ($lastCompletedSession->therapistProfile)
                    <a href="{{ route('therapists.book', $lastCompletedSession->therapistProfile) }}" class="btn coral" style="border-radius:999px;padding:10px 22px;">Book with them again</a>
                @endif
                <a href="{{ route('therapists.browse') }}" class="btn ghost" style="border-radius:999px;padding:10px 22px;">Browse other therapists</a>
            </div>
            <p style="margin-top:16px;">
                <a href="{{ route('sessions.history') }}" style="font-size:12.5px;color:var(--ink-500);">View your session history</a>
            </p>
        </div>
    @else
        <div class="card" style="text-align:center;padding:44px 24px;">
            @include('partials.mw-illustration', ['variant' => 'compass'])
            <h1 style="font-size:19px;font-weight:800;margin:14px 0 8px;color:var(--ink-900);">
                Intake complete
            </h1>
            <p style="font-size:13.5px;color:var(--ink-500);max-width:360px;margin:0 auto 18px;">
                Messaging will show up here as it gets built. You can already see your session history below.
            </p>
            <a href="{{ route('therapists.browse') }}" class="btn ghost" style="display:inline-block;width:auto;border-radius:999px;padding:11px 26px;">Browse therapists</a>
            <p style="margin-top:16px;">
                <a href="{{ route('sessions.history') }}" style="font-size:12.5px;color:var(--ink-500);">View your session history</a>
            </p>
        </div>
    @endif
@endif

@endsection
