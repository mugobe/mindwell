<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Mindwell</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Warm canvas background (2026-09-26 feedback: flat white pages read as too
           plain next to BetterHelp). Kept self-contained here rather than in app.css,
           since this sandbox has never actually had that file's real contents to edit
           safely. A soft cream base + a very faint botanical line-doodle pattern,
           mostly visible in the margins around cards/tables rather than under them. */
        body {
            background-color: #FBF3E7;
        }

        /* Client-facing pastel palette (2026-09-27 design pass) — a small family of soft,
           warm tints all derived from the existing teal/coral/cream identity, used for
           illustrations, chips and banners on client-facing pages only. Deliberately kept
           separate from the core --teal-*/--coral-*/--ink-* variables (defined in the real
           app.css this sandbox has never had visible contents for) so nothing here can
           collide with or override those. Admin/therapist pages don't reference these and
           are unaffected. */
        :root {
            --mw-teal-tint: #DCEDE8;
            --mw-coral-tint: #FCE1D3;
            --mw-sage: #DEE8CE;
            --mw-blush: #F5DCE2;
            --mw-gold: #F6E1A8;
            --mw-teal-dot: #6FA99B;
        }
    </style>
</head>
<body>

@php
    $verifiedTherapist = auth()->check()
        && auth()->user()->role === 'therapist'
        && auth()->user()->therapistProfile
        && auth()->user()->therapistProfile->credentials_verified;

    // Same warm-cream + faint botanical-line-doodle background used across every
    // content canvas (admin, therapist, client) -- see the note in <head> above.
    $mwCanvasBg = "background-color:#FBF3E7; background-image:url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27240%27 height=%27240%27 viewBox=%270 0 240 240%27%3E%3Cg fill=%27none%27 stroke=%27%232f6f66%27 stroke-width=%271.5%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 opacity=%270.07%27%3E%3Cg transform=%27translate(40,50) rotate(-12)%27%3E%3Cellipse cx=%270%27 cy=%27-8%27 rx=%274%27 ry=%277%27/%3E%3Cellipse cx=%270%27 cy=%278%27 rx=%274%27 ry=%277%27/%3E%3Cellipse cx=%27-8%27 cy=%270%27 rx=%277%27 ry=%274%27/%3E%3Cellipse cx=%278%27 cy=%270%27 rx=%277%27 ry=%274%27/%3E%3Ccircle cx=%270%27 cy=%270%27 r=%273%27/%3E%3C/g%3E%3Cg transform=%27translate(175,120) rotate(18)%27%3E%3Cpath d=%27M0 46 C 3 26 3 12 0 0%27/%3E%3Cpath d=%27M0 30 C 12 24 17 12 9 2%27/%3E%3Cpath d=%27M0 18 C -12 12 -16 1 -8 -6%27/%3E%3C/g%3E%3Cg transform=%27translate(55,190) rotate(8)%27%3E%3Cellipse cx=%270%27 cy=%27-7%27 rx=%273.5%27 ry=%276%27/%3E%3Cellipse cx=%270%27 cy=%277%27 rx=%273.5%27 ry=%276%27/%3E%3Cellipse cx=%27-7%27 cy=%270%27 rx=%276%27 ry=%273.5%27/%3E%3Cellipse cx=%277%27 cy=%270%27 rx=%276%27 ry=%273.5%27/%3E%3Ccircle cx=%270%27 cy=%270%27 r=%272.6%27/%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); background-repeat:repeat; background-size:240px 240px;";
@endphp

<div class="mw-topbar">
    <a href="{{ route('dashboard') }}" class="mw-brand">
        <span class="dot"></span> Mindwell
    </a>

    @auth
        @if (auth()->user()->role === 'admin')
            <div style="font-size:12px;font-weight:700;color:var(--ink-300);">Admin console</div>
        @elseif ($verifiedTherapist)
            <div style="font-size:12px;font-weight:700;color:var(--ink-300);">Therapist portal</div>
        @endif
        <div style="display:flex;align-items:center;gap:14px;">
            @if (auth()->user()->role === 'client')
                <a href="{{ route('sessions.history') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">My sessions</a>
                <a href="{{ route('journal.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">My journal</a>
                <a href="{{ route('subscription.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">My plan</a>
                <a href="{{ route('employer-seat.index') }}" style="font-size:13px;font-weight:600;color:var(--ink-500);">Employer benefit</a>
            @endif
            <span style="font-size:13px;color:var(--ink-500);">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn ghost sm">Log out</button>
            </form>
        </div>
    @endauth
</div>

@auth
    @if (auth()->user()->role === 'admin')
        {{-- Admin gets the full dashboard shell: dark sidebar + light content area,
             matching the approved prototype's admin panel. --}}
        <div class="dash">
            <div class="sidebar">
                <div class="side-brand"><span class="dot" style="width:8px;height:8px;border-radius:50%;background:var(--coral-500);"></span> Mindwell Admin</div>
                <a href="{{ route('admin.users.index') }}" class="side-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/></svg></span> Users
                </a>
                <a href="{{ route('admin.therapists.index') }}" class="side-link {{ request()->routeIs('admin.therapists.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z"/><path d="M9 12.5l2 2 4-4.5"/></svg></span> Credentialing
                </a>
                <a href="{{ route('admin.intake.index') }}" class="side-link {{ request()->routeIs('admin.intake.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3a1 1 0 011-1h4a1 1 0 011 1v1"/><path d="M9 10h6M9 14h6M9 18h4"/></svg></span> Intake Reviews
                </a>
                <a href="{{ route('admin.crisis.index') }}" class="side-link {{ request()->routeIs('admin.crisis.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M12 3.5l9 15.5H3l9-15.5z"/><path d="M12 10v4"/><circle cx="12" cy="17" r="0.9" fill="currentColor" stroke="none"/></svg></span> Crisis Flags
                </a>
                <a href="{{ route('admin.session-routing.index') }}" class="side-link {{ request()->routeIs('admin.session-routing.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M4 7c3 0 3 10 6 10s3-10 6-10 3 10 6 10"/><circle cx="4" cy="7" r="1.2" fill="currentColor" stroke="none"/><circle cx="16" cy="17" r="1.2" fill="currentColor" stroke="none"/></svg></span> Session Routing
                </a>
                <a href="{{ route('admin.closed-sessions.index') }}" class="side-link {{ request()->routeIs('admin.closed-sessions.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M7 3h7l4 4v14a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v4h4"/><path d="M9 13.5l2 2 4-4.5"/></svg></span> Session Reports
                </a>
                <a href="{{ route('admin.referrals.index') }}" class="side-link {{ request()->routeIs('admin.referrals.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M13 5l7 7-7 7"/><path d="M4 12h16"/></svg></span> Referrals
                </a>
                <a href="{{ route('admin.facilities.index') }}" class="side-link {{ request()->routeIs('admin.facilities.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M12 8v6M9 11h6"/></svg></span> Facilities
                </a>
                <a href="{{ route('admin.client-subscriptions.index') }}" class="side-link {{ request()->routeIs('admin.client-subscriptions.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/></svg></span> Client Subscriptions
                </a>
                <a href="{{ route('admin.employers.index') }}" class="side-link {{ request()->routeIs('admin.employers.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><rect x="4" y="3" width="9" height="18" rx="1"/><rect x="14" y="9" width="6" height="12" rx="1"/><path d="M7.5 7h1M10.5 7h1M7.5 11h1M10.5 11h1M7.5 15h1M10.5 15h1"/></svg></span> Employers
                </a>
                <a href="{{ route('admin.plans.index') }}" class="side-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/></svg></span> Subscription Plans
                </a>
                <a href="{{ route('admin.audit.index') }}" class="side-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M7 3h7l4 4v14a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 15h6M9 18h3"/></svg></span> Audit Log
                </a>
            </div>
            <div class="dash-main" style="{{ $mwCanvasBg }}">
                @if (session('status'))
                    <div class="mw-alert success">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mw-alert danger">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
    @elseif ($verifiedTherapist)
        {{-- Verified therapists get their own sidebar shell. Dashboard, Sessions and
             Schedule exist so far; the rest of the prototype's therapist nav
             (Clients, Messages, Earnings) gets added as those features are built. --}}
        <div class="dash">
            <div class="sidebar">
                <div class="side-brand"><span class="dot" style="width:8px;height:8px;border-radius:50%;background:var(--coral-500);"></span> Mindwell Pro</div>
                <a href="{{ route('dashboard') }}" class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M4 11l8-7 8 7"/><path d="M6 10v10h12V10"/></svg></span> Dashboard
                </a>
                <a href="{{ route('therapist.sessions.index') }}" class="side-link {{ request()->routeIs('therapist.sessions.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/><path d="M9 14l2 2 4-4"/></svg></span> Sessions
                </a>
                <a href="{{ route('therapist.reports.index') }}" class="side-link {{ request()->routeIs('therapist.reports.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M7 3h7l4 4v14a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v4h4"/><path d="M9 13.5l2 2 4-4.5"/></svg></span> Reports
                </a>
                <a href="{{ route('therapist.availability.index') }}" class="side-link {{ request()->routeIs('therapist.availability.*') ? 'active' : '' }}">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/><path d="M9 2h6"/></svg></span> Schedule
                </a>
            </div>
            <div class="dash-main" style="{{ $mwCanvasBg }}">
                @if (session('status'))
                    <div class="mw-alert success">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mw-alert danger">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
    @else
        {{-- Client / unverified therapist: plain content area, no sidebar. --}}
        <div style="max-width:960px;margin:0 auto;padding:28px;min-height:100vh;{{ $mwCanvasBg }}">
            @if (session('status'))
                <div class="mw-alert success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mw-alert danger">{{ session('error') }}</div>
            @endif
            @yield('content')
        </div>
    @endif
@else
    <div style="max-width:960px;margin:0 auto;padding:28px;min-height:100vh;{{ $mwCanvasBg }}">
        @yield('content')
    </div>
@endauth

</body>
</html>
