@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

@if (auth()->user()->role === 'admin')
    @include('admin._overview')

@elseif (auth()->user()->role === 'therapist')
    @php $profile = auth()->user()->therapistProfile; @endphp

    @if (! $profile || ! $profile->credentials_verified)
        {{-- Therapist onboarding (2026-11-03): where the application stands. --}}
        @include('apply._status-body', ['app' => \App\Models\TherapistApplication::forUser(auth()->user())->load('documents', 'user')])
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
            // 2026-11-03 staff refresh: the next session at the top, and clients' mood faces.
            // Only journal moods the client chose to SHARE with their therapist are used.
            $mwHero = $mwNextUp ?? $mwSoon->first();
            $mwClientIds = $mwToday->pluck('client_profile_id')->merge($mwSoon->pluck('client_profile_id'))
                ->merge(\App\Models\TherapySession::where('therapist_profile_id', $profile->id)->where('scheduled_at', '>=', now()->subDays(60))->pluck('client_profile_id'))
                ->filter()->unique()->values();
            $mwMoods = rescue(fn () => \App\Models\JournalEntry::whereIn('client_profile_id', $mwClientIds)
                ->where('shared_with_therapist', true)->whereNotNull('mood')->where('created_at', '>=', now()->subDays(14))
                ->orderByDesc('created_at')->get(['client_profile_id', 'mood', 'created_at'])->groupBy('client_profile_id'), collect(), false);
            $mwLastMood = fn ($cid) => optional($mwMoods->get($cid))->first()?->mood;
            $mwLow = $mwMoods->filter(fn ($rows) => $rows->count() >= 2 && $rows->take(2)->every(fn ($r) => $r->mood <= 2));
            $mwLowNames = \App\Models\ClientProfile::with('user')->whereIn('id', $mwLow->keys())->get()->map(fn ($c) => $c->user?->name)->filter()->values();
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
                $mwLow->isNotEmpty() ? ['icon' => 'pulse', 'tone' => 'warm', 'title' => 'Mood dropped this week', 'sub' => $mwLowNames->take(2)->implode(', ').($mwLowNames->count() > 2 ? ' and others' : '').': two low check-ins in a row', 'count' => $mwLow->count(), 'href' => route('therapist.clients.index'), 'cta' => 'View'] : null,
                $mwCounts['unread_messages'] ? ['icon' => 'msg', 'tone' => 'teal', 'title' => 'Unread messages', 'sub' => 'From your clients', 'count' => $mwCounts['unread_messages'], 'href' => route('messages.index'), 'cta' => 'Open'] : null,
                $mwCounts['check_ins_due'] ? ['icon' => 'pulse', 'tone' => 'teal', 'title' => 'Check-ins due', 'sub' => 'Clients due their two-weekly PHQ-9 / GAD-7', 'count' => $mwCounts['check_ins_due'], 'href' => route('therapist.clients.index'), 'cta' => 'See clients'] : null,
                $mwCounts['new_clients'] ? ['icon' => 'user', 'tone' => 'teal', 'title' => 'New clients', 'sub' => 'First session in the last 14 days. Read their intake.', 'count' => $mwCounts['new_clients'], 'href' => route('therapist.clients.index'), 'cta' => 'See clients'] : null,
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

        @if ($mwHero)
            @php
                $mwHC = $mwHero->clientProfile?->user;
                $mwHM = (int) floor(now()->diffInMinutes($mwHero->scheduled_at, false));
                $mwHWhen = $mwHero->scheduled_at->isToday()
                    ? ($mwHM <= 0 ? 'NOW' : ($mwHM < 60 ? 'IN '.$mwHM.' MIN' : 'IN '.intdiv($mwHM, 60).' H'))
                    : strtoupper($mwHero->scheduled_at->isTomorrow() ? 'Tomorrow' : $mwHero->scheduled_at->format('l'));
                $mwHMood = $mwLastMood($mwHero->client_profile_id);
            @endphp
            <div class="mw-hero-next">
                <span class="mw-dateblock glass"><span class="m">{{ strtoupper($mwHero->scheduled_at->format('M')) }}</span><span class="d">{{ $mwHero->scheduled_at->format('j') }}</span><span class="w">{{ $mwHero->scheduled_at->format('D') }}</span></span>
                <div style="flex:1;min-width:0;">
                    <div class="kick">NEXT SESSION · {{ $mwHWhen }}</div>
                    <div class="ttl">{{ $mwHero->scheduled_at->format('g:i A') }} with {{ $mwHC?->name ?? 'your client' }}</div>
                    <div class="meta">
                        <span>{{ $mwHero->isInPerson() ? 'In person'.($mwHero->venue ? ', '.$mwHero->venue->name : '') : 'Video' }} · {{ $mwHero->duration_minutes ?: config('mindwell.session_duration_minutes') }} min</span>
                        @if ($mwHMood)<span style="display:inline-flex;align-items:center;gap:5px;">Last check-in @include('partials.mood-face', ['mood' => $mwHMood, 'size' => 18])</span>@endif
                        @if ($mwOverview->isFirstWith($mwHero))<span style="color:#FFB766;font-weight:700;">First session</span>@endif
                    </div>
                </div>
                <div class="act">
                    @if (! $mwHero->isInPerson() && $mwHero->scheduled_at->isToday())
                        <a href="{{ route('sessions.room', $mwHero) }}" class="btn" style="background:#EE7800;color:#fff;border:0;height:42px;padding:0 20px;display:inline-flex;align-items:center;">{{ $mwHM <= 60 ? 'Join room' : 'Open room' }}</a>
                        <div class="note">Your client can join from {{ $mwHero->scheduled_at->copy()->subMinutes((int) config('mindwell.video_join_early_minutes', 10))->format('g:i A') }}</div>
                    @else
                        <a href="{{ route('therapist.clients.show', $mwHero->client_profile_id) }}" class="btn" style="background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.3);">Open client file</a>
                    @endif
                </div>
            </div>
        @endif

        <div class="mw-t-flow">
        <div class="mw-kpis mw-t-kpis">
            <a class="mw-kpi" href="{{ route('therapist.calendar', ['view' => 'day']) }}">
                <div class="k"><span class="ic" style="background:#E3EDF8;"><svg viewBox="0 0 24 24" fill="none" stroke="#034C8F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg></span>Sessions today</div>
                <div class="v">{{ $mwCounts['today'] }}</div>
                <div class="d">{{ $mwNextUp ? 'Next at '.$mwNextUp->scheduled_at->format('g:i A') : $mwCounts['upcoming'].' booked ahead' }}</div>
            </a>
            <a class="mw-kpi" href="{{ route('therapist.clients.index') }}">
                <div class="k"><span class="ic" style="background:#EEE8FA;"><svg viewBox="0 0 24 24" fill="none" stroke="#7B5CC4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c1-3.3 3.4-5 6-5s5 1.7 6 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.6c2.2.2 3.9 1.7 5 4.4"/></svg></span>Active clients</div>
                <div class="v">{{ $mwCounts['active_clients'] }}</div>
                <div class="d">{!! $mwCounts['new_clients'] ? '<span class="mw-chip green">+'.$mwCounts['new_clients'].'</span> new in 14 days' : 'Seen or booked in 60 days' !!}</div>
            </a>
            <a class="mw-kpi" href="{{ route('therapist.sessions.index') }}">
                <div class="k"><span class="ic" style="background:{{ $mwCounts['to_close'] ? '#FFEBD6' : '#DDF4EC' }};"><svg viewBox="0 0 24 24" fill="none" stroke="{{ $mwCounts['to_close'] ? '#EE7800' : '#00A87C' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 14l2 2 4-4"/></svg></span>Reports to write</div>
                <div class="v" style="{{ $mwCounts['to_close'] ? 'color:#EE7800;' : '' }}">{{ $mwCounts['to_close'] }}</div>
                <div class="d">{!! $mwCounts['to_close'] ? 'Sessions waiting to be closed' : '<span class="mw-chip green">All done</span>' !!}</div>
            </a>
            <a class="mw-kpi" href="{{ route('therapist.stats') }}">
                <div class="k"><span class="ic" style="background:#FFEBD6;"><svg viewBox="0 0 24 24" fill="none" stroke="#EE7800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></span>Sessions this month</div>
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
                                        @if ($mwKind === 'First session')<span style="color:#EE7800;font-weight:700;">First session</span>@else{{ $mwKind }}@endif
                                        · {{ $mwS->duration_minutes ?: config('mindwell.session_duration_minutes') }} min · {{ $mwS->isInPerson() ? 'In person'.($mwS->venue ? ', '.$mwS->venue->name : '') : 'Video' }}
                                    </div>
                                </div>
                                <div style="white-space:nowrap;display:flex;align-items:center;gap:10px;">
                                    @if ($mwM = $mwLastMood($mwS->client_profile_id))<span title="Last shared check-in">@include('partials.mood-face', ['mood' => $mwM, 'size' => 22])</span>@endif
                                    @include('partials.therapist-join', ['session' => $mwS])
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
        </div>
        <style>
            .mw-hero-next { display:flex; align-items:center; gap:18px; padding:20px 22px; border-radius:18px; margin-bottom:16px; color:#fff; background:linear-gradient(135deg, #034C8F 0%, #04305E 100%); box-shadow:0 14px 30px -18px rgba(3,31,63,.6); }
            .mw-hero-next .kick { font-size:11px; font-weight:800; letter-spacing:.08em; color:#7FE0C3; }
            .mw-hero-next .ttl { font-size:20px; font-weight:800; margin-top:3px; letter-spacing:-.01em; }
            .mw-hero-next .meta { display:flex; flex-wrap:wrap; gap:14px; margin-top:4px; font-size:13px; color:rgba(255,255,255,.82); align-items:center; }
            .mw-hero-next .act { text-align:right; }
            .mw-hero-next .note { font-size:11.5px; color:rgba(255,255,255,.7); margin-top:6px; }
            @media (max-width: 1100px) { .mw-ov-grid { grid-template-columns: 1fr !important; } }
            @media (max-width: 720px) {
                .mw-hero-next { flex-wrap:wrap; padding:16px; }
                .mw-hero-next .act { width:100%; text-align:left; }
                .mw-hero-next .act .btn { width:100%; justify-content:center; }
                .mw-t-flow { display:flex; flex-direction:column; gap:16px; }
                .mw-t-flow > .mw-ov-grid { order:1; }
                .mw-t-kpis { grid-template-columns: 1fr 1fr !important; order:2; margin:0 !important; }
                .mw-t-kpis .mw-kpi .v { font-size:22px; }
                .mw-t-kpis .mw-kpi .d { display:none; }
            }
        </style>
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

    @php
        // 2026-11-01 refresh: greeting hero + two columns (main / side).
        $mwFirst = explode(' ', trim(auth()->user()->name))[0] ?: auth()->user()->name;
        $mwHour = (int) now()->format('G');
        $mwHello = $mwHour < 12 ? 'Good morning' : ($mwHour < 17 ? 'Good afternoon' : 'Good evening');
        $mwRecent = $clientProfile
            ? \App\Models\TherapySession::where('client_profile_id', $clientProfile->id)
                ->whereIn('status', ['scheduled', 'completed', 'cancelled'])
                ->whereNotNull('scheduled_at')
                ->with('therapistProfile.user')
                ->orderByDesc('scheduled_at')->limit(3)->get()
            : collect();
        if ($mwNext) {
            $mwJoinFrom = $mwNext->scheduled_at->copy()->subMinutes((int) config('mindwell.video_join_early_minutes', 10));
            $mwCanJoin = now()->gte($mwJoinFrom);
            $mwWhen = $mwNext->scheduled_at->isToday() ? 'today' : ($mwNext->scheduled_at->isTomorrow() ? 'tomorrow' : 'on '.$mwNext->scheduled_at->format('l, j F'));
        }
        $mwCheckIn = \App\Models\CheckIn::statusFor($clientProfile);
        $mwToRate = \App\Models\SessionRating::pendingFor($clientProfile?->id);
        $mwAskAbout = $latestIntake && $clientProfile && \App\Models\ClientIntakeDetail::tableReady() && \App\Support\IntakeQuestions::anyOn() && ! \App\Models\ClientIntakeDetail::for($clientProfile->id);
        $mwFaces = [
            1 => ['#E5484D', 'M8.5 16.5c1-1.4 2.2-2 3.5-2s2.5.6 3.5 2'],
            2 => ['#F2994A', 'M9 16c.9-.8 1.9-1.2 3-1.2s2.1.4 3 1.2'],
            3 => ['#E3B341', 'M9 15.5h6'],
            4 => ['#3FB984', 'M9 14.5c.9.9 1.9 1.4 3 1.4s2.1-.5 3-1.4'],
            5 => ['#0E9F6E', 'M8.5 14c1 1.6 2.2 2.4 3.5 2.4s2.5-.8 3.5-2.4'],
        ];
    @endphp

    <style>
        .mwd-hero { position:relative; overflow:hidden; display:flex; align-items:center; gap:24px; padding:30px 34px; margin-bottom:26px; border-radius:26px;
            background: linear-gradient(120deg, #EAF2FC 0%, #EEF8F4 55%, #FFF4E6 100%); }
        .mwd-hero-text { position:relative; z-index:1; flex:1; min-width:0; }
        .mwd-date { font-size:13px; font-weight:700; color:var(--mw-navy); opacity:.75; }
        .mwd-hero h1 { font-size:32px; line-height:1.15; font-weight:800; letter-spacing:-.025em; color:var(--mw-navy-900); margin:6px 0 8px; }
        .mwd-hero p { font-size:15px; color:var(--ink-700); margin:0; max-width:520px; line-height:1.55; }
        .mwd-hero-act { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:18px; }
        .mwd-hero-act .btn { padding:12px 24px; }
        .mwd-hero-act .hint { font-size:12.5px; color:var(--ink-500); }
        .mwd-art { position:relative; z-index:1; flex:0 0 220px; width:220px; height:170px; }
        .mwd-art .float { animation: mwdFloat 6s ease-in-out infinite; transform-origin:center; }
        .mwd-art .float.b { animation-delay:-2s; animation-duration:7s; }
        .mwd-art .sway { animation: mwdSway 5s ease-in-out infinite; transform-origin:110px 150px; }
        @keyframes mwdFloat { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-6px); } }
        @keyframes mwdSway { 0%,100% { transform:rotate(-2deg); } 50% { transform:rotate(2deg); } }

        .mwd-grid { display:grid; grid-template-columns: minmax(0, 1fr) 340px; gap:22px; align-items:start; }
        .mwd-col { display:flex; flex-direction:column; gap:18px; min-width:0; }
        .mwd-col > .mw-note { margin:0; }

        .mwd-next { display:flex; align-items:center; gap:20px; padding:22px; flex-wrap:wrap; }
        .mwd-cal { flex:0 0 76px; height:84px; border-radius:18px; background:var(--mw-navy); color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(3,76,143,.25); }
        .mwd-cal .m { font-size:11px; font-weight:800; letter-spacing:.12em; opacity:.8; }
        .mwd-cal .d { font-size:30px; font-weight:800; line-height:1; margin:2px 0; }
        .mwd-cal .w { font-size:11px; font-weight:700; opacity:.8; }
        .mwd-next .info { flex:1; min-width:200px; }
        .mwd-eyebrow { font-size:12px; font-weight:800; color:var(--mw-green); letter-spacing:.04em; text-transform:uppercase; }
        .mwd-next .t { font-size:19px; font-weight:800; color:var(--ink-900); margin-top:4px; letter-spacing:-.01em; }
        .mwd-next .s { font-size:13.5px; color:var(--ink-500); margin-top:3px; }
        .mwd-next .act { display:flex; flex-direction:column; align-items:flex-end; gap:6px; }
        .mwd-next .act .hint { font-size:12px; color:var(--ink-500); }
        .mwd-live { display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--mw-green-500); margin-right:6px; vertical-align:middle; box-shadow:0 0 0 0 rgba(0,184,136,.5); animation: mwdPing 1.8s ease-out infinite; }
        @keyframes mwdPing { 0% { box-shadow:0 0 0 0 rgba(0,184,136,.5); } 100% { box-shadow:0 0 0 10px rgba(0,184,136,0); } }

        .mwd-list { list-style:none; margin:0; padding:0; }
        .mwd-list li { display:flex; align-items:center; gap:14px; padding:12px 0; }
        .mwd-list li + li { border-top:1px solid var(--mwc-line); }
        .mwd-dot { width:40px; height:40px; flex:0 0 40px; border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; background:var(--mw-navy-tint); color:var(--mw-navy); line-height:1; }
        .mwd-dot b { font-size:15px; } .mwd-dot span { font-size:9.5px; font-weight:800; letter-spacing:.06em; margin-top:2px; }
        .mwd-list .t { font-size:14px; font-weight:700; color:var(--ink-900); }
        .mwd-list .s { font-size:12.5px; color:var(--ink-500); margin-top:1px; }
        .mwd-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:6px; }
        .mwd-head a { font-size:13px; font-weight:700; color:var(--mw-navy); text-decoration:none; }
        .mwd-head a:hover { text-decoration:underline; }

        .mwd-moods { display:grid; grid-template-columns:repeat(5, 1fr); gap:6px; margin-top:12px; }
        .mwd-mood { display:flex; flex-direction:column; align-items:center; gap:6px; padding:10px 2px 8px; border:0; border-radius:14px; background:transparent; cursor:pointer; font:inherit; font-size:11px; font-weight:700; color:var(--ink-500); transition:background .18s, transform .18s; }
        .mwd-mood svg { width:36px; height:36px; transition:transform .25s cubic-bezier(.3,1.6,.5,1); }
        .mwd-mood:hover { background:var(--mw-navy-tint); color:var(--ink-900); }
        .mwd-mood:hover svg { transform:scale(1.15) rotate(-4deg); }
        .mwd-mood.is-on { background:var(--mw-navy-tint); color:var(--ink-900); }
        .mwd-mood.is-off { opacity:.35; }

        .mwd-quick { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:12px; }
        .mwd-tile { display:flex; flex-direction:column; gap:10px; padding:14px; border-radius:16px; background:#F7F9FC; text-decoration:none; color:var(--ink-900); font-size:13px; font-weight:700; line-height:1.3; transition:background .18s, transform .2s cubic-bezier(.2,.8,.2,1); }
        .mwd-tile:hover { background:#fff; transform:translateY(-2px); box-shadow:0 8px 22px rgba(3,40,80,.09); }
        .mwd-tile .ic { width:36px; height:36px; border-radius:11px; display:flex; align-items:center; justify-content:center; }
        .mwd-tile .ic svg { width:19px; height:19px; }

        @media (max-width: 1100px) { .mwd-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) {
            .mwd-hero { padding:22px 20px; border-radius:22px; }
            .mwd-hero h1 { font-size:25px; }
            .mwd-art { display:none; }
            .mwd-next .act { align-items:stretch; width:100%; }
        }
        @media (prefers-reduced-motion: reduce) { .mwd-art *, .mwd-live { animation:none !important; } }
    </style>

    {{-- Greeting hero: one clear next step for where the client is right now. --}}
    <section class="mwd-hero">
        <div class="mwd-hero-text">
            <div class="mwd-date">{{ now()->format('l, j F') }}</div>
            <h1>{{ $mwHello }}, {{ $mwFirst }}</h1>
            @if (! $latestIntake)
                <p>Welcome to Mindwell. Start with a short assessment so we can match you with the right therapist.</p>
                <div class="mwd-hero-act">
                    <a href="{{ route('intake.show') }}" class="btn coral">Start my assessment</a>
                    <span class="hint">About 5 minutes. Private and confidential.</span>
                </div>
            @elseif ($mwNext)
                <p>Your next session with {{ $mwNext->therapistProfile?->user?->name ?? 'your therapist' }} is {{ $mwWhen }} at {{ $mwNext->scheduled_at->format('g:i A') }}.</p>
                <div class="mwd-hero-act">
                    @if ($mwNext->isInPerson())
                        <a href="{{ route('sessions.connected', $mwNext) }}" class="btn primary">Where to go</a>
                    @elseif ($mwCanJoin)
                        <a href="{{ route('sessions.room', $mwNext) }}" class="btn coral"><span class="mwd-live" style="background:#fff;"></span>Join session</a>
                    @else
                        <a href="{{ route('sessions.room', $mwNext) }}" class="btn primary">Open session room</a>
                        <span class="hint">You can join from {{ $mwJoinFrom->format('g:i A') }}{{ $mwJoinFrom->isToday() ? '' : ' on '.$mwJoinFrom->format('D j M') }}.</span>
                    @endif
                </div>
            @elseif ($inProgress)
                <p>
                    @switch($latestSession->status)
                        @case('pending_payment') You have a session waiting for payment. Pick up where you left off to confirm it. @break
                        @case('routing') You are in the lobby, waiting to be connected to a therapist. @break
                        @case('pending_approval') Your booking is with our team. We will confirm it soon. @break
                        @case('scheduled') You have been matched with a therapist. See the details. @break
                    @endswitch
                </p>
                <div class="mwd-hero-act">
                    <a href="{{ route(match($latestSession->status) {
                        'pending_payment' => 'sessions.checkout',
                        'routing' => 'sessions.lobby',
                        'pending_approval' => 'sessions.pending-approval',
                        'scheduled' => 'sessions.connected',
                    }, $latestSession) }}" class="btn coral">Continue</a>
                </div>
            @elseif ($lastCompletedSession)
                <p>Ready for another session with {{ $lastCompletedSession->therapistProfile?->user?->name ?? 'your therapist' }}?</p>
                <div class="mwd-hero-act">
                    @if ($lastCompletedSession->therapistProfile)
                        <a href="{{ route('therapists.book', $lastCompletedSession->therapistProfile) }}" class="btn coral">Book again</a>
                    @endif
                    <a href="{{ route('therapists.browse') }}" class="btn ghost" style="border-radius:999px;">See other therapists</a>
                </div>
            @else
                <p>Your assessment is done. Choose a therapist and a time that suits you.</p>
                <div class="mwd-hero-act">
                    <a href="{{ route('therapists.browse') }}" class="btn coral">Find a therapist</a>
                </div>
            @endif
        </div>
        <svg class="mwd-art" viewBox="0 0 220 170" aria-hidden="true">
            <ellipse class="float" cx="78" cy="96" rx="70" ry="58" fill="#D7E6F7"/>
            <ellipse class="float b" cx="160" cy="70" rx="46" ry="40" fill="#CDEFE2"/>
            <circle class="float b" cx="186" cy="24" r="13" fill="#FFD29E"/>
            <circle class="float" cx="28" cy="30" r="7" fill="#F88000" opacity=".55"/>
            <g class="sway" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M110 152 C 110 120 108 98 112 70" stroke="#034C8F" stroke-width="3.5"/>
                <path d="M111 104 C 92 104 76 94 72 74 C 92 72 106 84 111 104 Z" fill="#00B888" stroke="#00A87C" stroke-width="2"/>
                <path d="M111 88 C 128 86 142 74 144 54 C 124 54 112 68 111 88 Z" fill="#34C79E" stroke="#00A87C" stroke-width="2"/>
                <path d="M112 70 C 104 58 104 44 114 34 C 122 46 120 60 112 70 Z" fill="#F88000" stroke="#E07200" stroke-width="2"/>
            </g>
            <path d="M80 154 h60" stroke="#034C8F" stroke-width="3.5" stroke-linecap="round" opacity=".25"/>
        </svg>
    </section>

    <div class="mwd-grid">
        <div class="mwd-col mwc-stagger">
            @if ($mwNext)
                <div class="card mwd-next">
                    <div class="mwd-cal" aria-hidden="true">
                        <span class="m">{{ strtoupper($mwNext->scheduled_at->format('M')) }}</span>
                        <span class="d">{{ $mwNext->scheduled_at->format('j') }}</span>
                        <span class="w">{{ $mwNext->scheduled_at->format('D') }}</span>
                    </div>
                    <div class="info">
                        <div class="mwd-eyebrow">Next session, {{ $mwNext->scheduled_at->isToday() ? 'today' : ($mwNext->scheduled_at->isTomorrow() ? 'tomorrow' : 'in '.(int) abs(now()->startOfDay()->diffInDays($mwNext->scheduled_at->copy()->startOfDay())).' days') }}</div>
                        <div class="t">{{ $mwNext->scheduled_at->format('g:i A') }} with {{ $mwNext->therapistProfile?->user?->name ?? 'your therapist' }}</div>
                        <div class="s">{{ $mwNext->isInPerson() ? 'In person' : 'Video session' }}, {{ $mwNext->duration_minutes ?: config('mindwell.session_duration_minutes') }} minutes</div>
                        @if ($mwNext->isInPerson())
                            <div class="s">{{ $mwNext->locationText() ?? 'Our team will confirm the place.' }}</div>
                        @endif
                    </div>
                    <div class="act">
                        @if ($mwNext->isInPerson())
                            <a href="{{ route('sessions.connected', $mwNext) }}" class="btn ghost sm" style="border-radius:999px;">Where to go</a>
                        @else
                            <a href="{{ route('sessions.room', $mwNext) }}" class="btn {{ $mwCanJoin ? 'coral' : 'ghost' }} sm" style="border-radius:999px;padding:9px 18px;">{{ $mwCanJoin ? 'Join now' : 'Session room' }}</a>
                        @endif
                        @if ($mwNext->scheduled_at->isFuture())
                            <a href="{{ route('sessions.change', $mwNext) }}" style="font-size:12.5px;font-weight:700;color:var(--ink-500);">Move or cancel</a>
                        @endif
                    </div>
                </div>
            @endif

            @if ($mwCheckIn['due'])
                <div class="mw-note event">
                    <div class="mw-note-body">
                        <div class="mw-note-title">Time for your check-in</div>
                        <div class="mw-note-text">It takes about 3 minutes and helps you and your therapist see how things are changing.</div>
                    </div>
                    <div class="mw-note-act"><a href="{{ route('progress.check-in') }}" class="mw-pill-btn filled">Start check-in</a></div>
                </div>
            @endif

            @if ($mwToRate)
                <div class="mw-note star">
                    <div class="mw-note-body">
                        <div class="mw-note-title">How was your session with {{ $mwToRate->therapistProfile?->user?->name ?? 'your therapist' }}?</div>
                        <div class="mw-note-text">{{ $mwToRate->scheduled_at->format('l, M j') }}. It only takes a few seconds.</div>
                    </div>
                    <div class="mw-note-act">
                        <form method="POST" action="{{ route('sessions.rate.store', $mwToRate) }}" style="margin:0;">
                            @csrf
                            <input type="hidden" name="skip" value="1">
                            <button type="submit" class="mw-pill-btn">Not now</button>
                        </form>
                        <a href="{{ route('sessions.rate', $mwToRate) }}" class="mw-pill-btn filled">Rate it</a>
                    </div>
                </div>
            @endif

            @if ($mwAskAbout)
                <div class="mw-note chat">
                    <div class="mw-note-body">
                        <div class="mw-note-title">Tell your therapist a bit about you</div>
                        <div class="mw-note-text">What brings you here and what you hope for. It takes a couple of minutes and every question is optional.</div>
                    </div>
                    <div class="mw-note-act">
                        <form method="POST" action="{{ route('about.dismiss') }}">@csrf<button type="submit" class="mw-pill-btn">Not now</button></form>
                        <a href="{{ route('about.edit') }}" class="mw-pill-btn filled">Start</a>
                    </div>
                </div>
            @endif

            @if (! auth()->user()->emergency_contact_name)
                <div class="mw-note person">
                    <div class="mw-note-body">
                        <div class="mw-note-title">Add an emergency contact</div>
                        <div class="mw-note-text">Someone we can call if we are ever worried about your safety. Only your care team can see it.</div>
                    </div>
                    <div class="mw-note-act"><a href="{{ route('settings.details') }}" class="mw-pill-btn">Add contact</a></div>
                </div>
            @endif

            <div class="card">
                <div class="mwd-head">
                    <h2 class="mwc-h2" style="margin:0;">Recent sessions</h2>
                    <a href="{{ route('sessions.history') }}">See all</a>
                </div>
                @if ($mwRecent->isEmpty())
                    <div style="display:flex;align-items:center;gap:16px;padding:8px 0 4px;">
                        @include('partials.mw-illustration', ['variant' => 'clipboard', 'size' => 84])
                        <div style="font-size:13.5px;color:var(--ink-500);line-height:1.55;">
                            No sessions yet. Once you book one, it will show here.
                        </div>
                    </div>
                @else
                    <ul class="mwd-list">
                        @foreach ($mwRecent as $mwR)
                            @php
                                [$mwPc, $mwPl] = match ($mwR->status) {
                                    'scheduled' => ['teal', $mwR->scheduled_at->isPast() ? 'Booked' : 'Upcoming'],
                                    'completed' => ['success', 'Completed'],
                                    'cancelled' => ['danger', 'Cancelled'],
                                    default => ['', ucfirst($mwR->status)],
                                };
                            @endphp
                            <li>
                                <div class="mwd-dot"><b>{{ $mwR->scheduled_at->format('j') }}</b><span>{{ strtoupper($mwR->scheduled_at->format('M')) }}</span></div>
                                <div style="flex:1;min-width:0;">
                                    <div class="t">{{ $mwR->therapistProfile?->user?->name ?? 'Therapist' }}</div>
                                    <div class="s">{{ $mwR->scheduled_at->format('l, g:i A') }}, {{ $mwR->isInPerson() ? 'in person' : 'video' }}</div>
                                </div>
                                <span class="pill {{ $mwPc }}">{{ $mwPl }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <aside class="mwd-col mwc-stagger">
            @if ($latestIntake)
                <div class="card">
                    <h2 class="mwc-h2" style="margin:0;">How are you feeling today?</h2>
                    @if ($mwMoodToday)
                        <div class="mwc-sub">You said <strong>{{ strtolower(\App\Models\JournalEntry::MOODS[$mwMoodToday] ?? '') }}</strong>. You can add a note in your journal.</div>
                    @else
                        <div class="mwc-sub">Tap one. It is saved privately in your journal.</div>
                    @endif
                    <form method="POST" action="{{ route('journal.store') }}" class="mwd-moods">
                        @csrf
                        <input type="hidden" name="return" value="dashboard">
                        @foreach (\App\Models\JournalEntry::MOODS as $mwMoodValue => $mwMoodLabel)
                            @php [$mwFc, $mwFm] = $mwFaces[$mwMoodValue] ?? ['#999', 'M9 15.5h6']; @endphp
                            <button type="submit" name="mood" value="{{ $mwMoodValue }}"
                                    class="mwd-mood {{ $mwMoodToday ? ($mwMoodToday == $mwMoodValue ? 'is-on' : 'is-off') : '' }}"
                                    @if ($mwMoodToday) disabled @endif aria-label="{{ $mwMoodLabel }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10.5" fill="{{ $mwFc }}" opacity=".18"/>
                                    <circle cx="12" cy="12" r="10.5" fill="none" stroke="{{ $mwFc }}" stroke-width="1.5"/>
                                    <circle cx="9" cy="10" r="1.2" fill="{{ $mwFc }}"/><circle cx="15" cy="10" r="1.2" fill="{{ $mwFc }}"/>
                                    <path d="{{ $mwFm }}" fill="none" stroke="{{ $mwFc }}" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                                {{ $mwMoodLabel }}
                            </button>
                        @endforeach
                    </form>
                    @if ($mwMoodToday)
                        <a href="{{ route('journal.index') }}" style="display:inline-block;margin-top:10px;font-size:13px;font-weight:700;color:var(--mw-navy);">Open my journal</a>
                    @endif
                </div>
            @endif

            <div class="card">
                <h2 class="mwc-h2" style="margin:0;">Quick links</h2>
                <div class="mwd-quick">
                    <a href="{{ route('therapists.browse') }}" class="mwd-tile">
                        <span class="ic" style="background:#FFEBD6;color:#C25E00;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="15" rx="2.5"/><path d="M4 9.5h16M8.5 3v4M15.5 3v4M12 12.5v5M9.5 15h5"/></svg></span>
                        Book a session
                    </a>
                    <a href="{{ route('messages.index') }}" class="mwd-tile">
                        <span class="ic" style="background:var(--mw-navy-tint);color:var(--mw-navy);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5h16a1 1 0 011 1v9a1 1 0 01-1 1H10l-4.5 3.5V16.5H4a1 1 0 01-1-1v-9a1 1 0 011-1z"/></svg></span>
                        Message my therapist
                    </a>
                    <a href="{{ route('journal.index') }}" class="mwd-tile">
                        <span class="ic" style="background:var(--mw-green-tint);color:var(--mw-green);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h11a1.5 1.5 0 011.5 1.5v15H7a1.5 1.5 0 01-1.5-1.5V4A.5.5 0 016 3.5z"/><path d="M9.5 8h5M9.5 11h3"/></svg></span>
                        Write in my journal
                    </a>
                    <a href="{{ route('progress.index') }}" class="mwd-tile">
                        <span class="ic" style="background:#F1E8FB;color:#7A4CC2;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16"/><path d="M5 15l4-4 3 3 6-6"/><path d="M15 8h3v3"/></svg></span>
                        See my progress
                    </a>
                </div>
            </div>

            <div class="mw-note shield top">
                <div class="mw-note-body">
                    <div class="mw-note-title">Your privacy comes first</div>
                    <div class="mw-note-text">Your sessions and reports are confidential. Only you and your therapist can see them.</div>
                </div>
            </div>
        </aside>
    </div>
@endif

@endsection
