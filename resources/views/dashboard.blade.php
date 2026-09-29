@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

@if (auth()->user()->role === 'admin')
    @php
        $crisisCount = \App\Models\IntakeAssessment::where('risk_level', 'crisis')->count();
        $highRiskCount = \App\Models\IntakeAssessment::whereIn('risk_level', ['crisis', 'high'])->count();
        $openFlagCount = \App\Models\CrisisFlag::where('status', 'open')->count();
        $waitingCount = \App\Models\TherapySession::where('status', 'routing')->count();
        $pendingReferralCount = \App\Models\TherapySession::where('closure_outcome', 'refer_to_specialist')->whereNull('referral_facility_id')->count();
        $pendingApprovalCount = \App\Models\TherapySession::where('status', 'pending_approval')->count();
    @endphp
    <div class="dash-head">
        <h1>Platform overview</h1>
        <span class="pill teal">Welcome back, {{ auth()->user()->name }}</span>
    </div>
    @php
        $icStyle = 'width:13px;height:13px;margin-right:5px;vertical-align:-2px;';
    @endphp
    <div class="stat-row">
        <div class="stat-tile">
            <div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="{{ $icStyle }}"><circle cx="9" cy="8" r="3"/><path d="M2.5 19c1.2-3 3.6-4.6 6.5-4.6s5.3 1.6 6.5 4.6"/><path d="M16 8.2a2.6 2.6 0 110-5 2.6 2.6 0 010 5z"/><path d="M15 14.8c2.4.4 4.2 1.8 5.2 4.2"/></svg>Total users</div>
            <div class="value">{{ \App\Models\User::count() }}</div>
        </div>
        <div class="stat-tile">
            <div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="{{ $icStyle }}"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/><path d="M9 2h6"/></svg>Pending credentialing</div>
            <div class="value">{{ \App\Models\TherapistProfile::where('credentials_verified', false)->count() }}</div>
        </div>
        <div class="stat-tile">
            <div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="{{ $icStyle }}"><path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z"/><path d="M9 12.5l2 2 4-4.5"/></svg>Verified therapists</div>
            <div class="value">{{ \App\Models\TherapistProfile::where('credentials_verified', true)->count() }}</div>
        </div>
        <div class="stat-tile" style="{{ $highRiskCount > 0 ? 'border-color:var(--danger);' : '' }}">
            <div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="{{ $icStyle }}"><path d="M12 3.5l9 15.5H3l9-15.5z"/><path d="M12 10v4"/><circle cx="12" cy="17" r="0.9" fill="currentColor" stroke="none"/></svg>High-risk / crisis intakes</div>
            <div class="value" style="{{ $highRiskCount > 0 ? 'color:var(--danger);' : '' }}">{{ $highRiskCount }}</div>
        </div>
        <div class="stat-tile" style="{{ $openFlagCount > 0 ? 'border-color:var(--danger);' : '' }}">
            <div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="{{ $icStyle }}"><path d="M5 3v18"/><path d="M5 4h11l-2.5 3.5L16 11H5"/></svg>Open crisis flags</div>
            <div class="value" style="{{ $openFlagCount > 0 ? 'color:var(--danger);' : '' }}">{{ $openFlagCount }}</div>
        </div>
        <div class="stat-tile">
            <div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="{{ $icStyle }}"><path d="M4 7c3 0 3 10 6 10s3-10 6-10 3 10 6 10"/><circle cx="4" cy="7" r="1.2" fill="currentColor" stroke="none"/><circle cx="16" cy="17" r="1.2" fill="currentColor" stroke="none"/></svg>Waiting for routing</div>
            <div class="value">{{ $waitingCount }}</div>
        </div>
        <div class="stat-tile" style="{{ $pendingReferralCount > 0 ? 'border-color:var(--danger);' : '' }}">
            <div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="{{ $icStyle }}"><path d="M13 5l7 7-7 7"/><path d="M4 12h16"/></svg>Awaiting referral</div>
            <div class="value" style="{{ $pendingReferralCount > 0 ? 'color:var(--danger);' : '' }}">{{ $pendingReferralCount }}</div>
        </div>
        <div class="stat-tile" style="{{ $pendingApprovalCount > 0 ? 'border-color:var(--danger);' : '' }}">
            <div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="{{ $icStyle }}"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>Bookings awaiting approval</div>
            <div class="value" style="{{ $pendingApprovalCount > 0 ? 'color:var(--danger);' : '' }}">{{ $pendingApprovalCount }}</div>
        </div>
    </div>
    <div class="card">
        <div class="section-title">Quick links</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('admin.users.index') }}" class="btn ghost sm">All users</a>
            <a href="{{ route('admin.therapists.index') }}" class="btn ghost sm">Therapist approvals</a>
            <a href="{{ route('admin.intake.index') }}" class="btn ghost sm">Intake reviews</a>
            <a href="{{ route('admin.crisis.index') }}" class="btn ghost sm">Crisis flags</a>
            <a href="{{ route('admin.session-routing.index') }}" class="btn ghost sm">Session routing</a>
            <a href="{{ route('admin.closed-sessions.index') }}" class="btn ghost sm">Session reports</a>
            <a href="{{ route('admin.referrals.index') }}" class="btn ghost sm">Referrals</a>
            <a href="{{ route('admin.facilities.index') }}" class="btn ghost sm">Facilities</a>
            <a href="{{ route('admin.client-subscriptions.index') }}" class="btn ghost sm">Client subscriptions</a>
            <a href="{{ route('admin.employers.index') }}" class="btn ghost sm">Employers</a>
            <a href="{{ route('admin.plans.index') }}" class="btn ghost sm">Subscription plans</a>
            <a href="{{ route('admin.audit.index') }}" class="btn ghost sm">Audit log</a>
        </div>
    </div>

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
        @php
            $upcomingCount = \App\Models\TherapySession::where('therapist_profile_id', $profile->id)->where('status', 'scheduled')->count();
        @endphp
        <div class="dash-head">
            <h1>Good to see you, {{ auth()->user()->name }}</h1>
            <span class="pill success">Credentials verified ✅</span>
        </div>

        @if ($upcomingCount > 0)
            <div class="card" style="margin-bottom:20px;">
                <div class="section-title">You have {{ $upcomingCount }} session{{ $upcomingCount === 1 ? '' : 's' }} to close out</div>
                <p style="font-size:13px;color:var(--ink-500);margin-bottom:14px;">
                    Once a session's happened, close it out with an outcome and a short report.
                </p>
                <a href="{{ route('therapist.sessions.index') }}" class="btn coral sm">View sessions</a>
            </div>
        @endif

        <div class="card">
            <div class="section-title">Set up your schedule</div>
            <p style="font-size:13px;color:var(--ink-500);margin-bottom:14px;">
                Clients can only book time you've marked as open. Add your weekly availability to start
                appearing in the matching results.
            </p>
            <a href="{{ route('therapist.availability.index') }}" class="btn primary sm">Manage availability</a>
        </div>
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