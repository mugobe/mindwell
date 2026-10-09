{{-- Sidebar navigation (2026-10-08 redesign, Collins: "more professional … organized in the
     appropriate flow … our orange color"). Grouped like standard admin consoles (Stripe,
     Linear, Zendesk): section labels, one accent colour for the current page and for
     counts that need attention. Shared by the admin and therapist shells.
     Needs: $mwNavRole ('admin' | 'therapist'), $mwUnread. --}}
@php
    $ic = [
        'home' => '<path d="M4 11l8-7 8 7"/><path d="M6 10v10h12V10"/>',
        'inbox' => '<path d="M4 13l2.5-8h11L20 13"/><path d="M4 13v6h16v-6h-5l-1 2h-4l-1-2H4z"/>',
        'alert' => '<path d="M12 3.5l9 15.5H3l9-15.5z"/><path d="M12 10v4"/><circle cx="12" cy="17" r=".9" fill="currentColor" stroke="none"/>',
        'route' => '<circle cx="6" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 6h6a4 4 0 010 8h-4a4 4 0 000 8h6"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/>',
        'swap' => '<path d="M4 8h12l-3-3M20 16H8l3 3"/>',
        'notes' => '<path d="M7 3h7l4 4v14H6V3h1z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 15h6M9 18h3"/>',
        'share' => '<circle cx="6" cy="12" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 11l8-4M8 13l8 4"/>',
        'pulse' => '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
        'star' => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5z"/>',
        'clients' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c1-3.3 3.4-5 6-5s5 1.7 6 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.6c2.2.2 3.9 1.7 5 4.4"/>',
        'badge' => '<path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z"/><path d="M9 12.5l2 2 4-4.5"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/>',
        'card' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>',
        'repeat' => '<path d="M17 3l3 3-3 3"/><path d="M20 6H8a4 4 0 00-4 4v1"/><path d="M7 21l-3-3 3-3"/><path d="M4 18h12a4 4 0 004-4v-1"/>',
        'layers' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>',
        'ticket' => '<path d="M4 7h16v3a2 2 0 000 4v3H4v-3a2 2 0 000-4V7z"/><path d="M10 7v10"/>',
        'building' => '<rect x="4" y="3" width="9" height="18" rx="1"/><rect x="14" y="9" width="6" height="12" rx="1"/><path d="M7.5 7h2M7.5 11h2M7.5 15h2"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0119 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'hospital' => '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M12 8v6M9 11h6"/>',
        'log' => '<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'chat' => '<path d="M4 5.5h16a1 1 0 011 1v9a1 1 0 01-1 1H10l-4.5 3.5V16.5H4a1 1 0 01-1-1v-9a1 1 0 011-1z"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.6 1.6 0 00-1.8-.3 1.6 1.6 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.6 1.6 0 00-1-1.5 1.6 1.6 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.6 1.6 0 00.3-1.8 1.6 1.6 0 00-1.5-1H3a2 2 0 110-4h.1a1.6 1.6 0 001.5-1 1.6 1.6 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.6 1.6 0 001.8.3H9a1.6 1.6 0 001-1.5V3a2 2 0 114 0v.1a1.6 1.6 0 001 1.5 1.6 1.6 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.6 1.6 0 00-.3 1.8V9a1.6 1.6 0 001.5 1H21a2 2 0 110 4h-.1a1.6 1.6 0 00-1.5 1z"/>',
    ];

    // Counts that need someone's attention. Each is wrapped so a missing table can never break the page.
    $n = fn (callable $q) => (int) rescue($q, 0, false);

    if ($mwNavRole === 'admin') {
        $mwGroups = [
            [null, [
                ['dashboard', 'dashboard', 'Overview', 'home', 0, false],
            ]],
            ['Care', [
                ['admin.intake.index', 'admin.intake.*', 'Intake reviews', 'inbox', 0, false],
                ['admin.crisis.index', 'admin.crisis.*', 'Crisis flags', 'alert', $n(fn () => \App\Models\CrisisFlag::where('status', 'open')->count()), true],
                ['admin.session-routing.index', 'admin.session-routing.*', 'Session routing', 'route', $n(fn () => \App\Models\TherapySession::whereIn('status', ['routing', 'pending_approval'])->count()), false],
                ['admin.calendar', 'admin.calendar', 'Calendar', 'calendar', 0, false],
                ['admin.schedule-requests.index', 'admin.schedule-requests.*', 'Schedule requests', 'swap', $n(fn () => \App\Models\ScheduleChangeRequest::where('status', 'pending')->count() + \App\Models\TimeOff::where('status', 'pending')->count()), false],
                ['admin.closed-sessions.index', 'admin.closed-sessions.*', 'Session reports', 'notes', 0, false],
                ['admin.referrals.index', 'admin.referrals.*', 'Referrals', 'share', $n(fn () => \App\Models\TherapySession::where('closure_outcome', 'refer_to_specialist')->whereNull('referral_facility_id')->count()), false],
                ['admin.check-ins.index', 'admin.check-ins.*', 'Check-ins', 'pulse', 0, false],
                ['admin.ratings.index', 'admin.ratings.*', 'Session ratings', 'star', 0, false],
            ]],
            ['People', [
                ['admin.clients.index', 'admin.clients.*', 'Clients', 'clients', 0, false],
                ['admin.therapists.index', 'admin.therapists.*', 'Therapists', 'badge', $n(fn () => \App\Models\TherapistProfile::where('credentials_verified', false)->count()), false],
                ['admin.users.index', 'admin.users.*', 'All users', 'user', 0, false],
            ]],
            ['Billing', [
                ['admin.payments.index', 'admin.payments.*', 'Payments', 'card', 0, false],
                ['admin.client-subscriptions.index', 'admin.client-subscriptions.*', 'Subscriptions', 'repeat', 0, false],
                ['admin.plans.index', 'admin.plans.*', 'Plans & pricing', 'layers', 0, false],
                ['admin.vouchers.index', 'admin.vouchers.*', 'Vouchers', 'ticket', 0, false],
                ['admin.employers.index', 'admin.employers.*', 'Employers', 'building', 0, false],
            ]],
            ['Setup', [
                ['admin.venues.index', 'admin.venues.*', 'Venues', 'pin', 0, false],
                ['admin.facilities.index', 'admin.facilities.*', 'Referral facilities', 'hospital', 0, false],
                ['admin.audit.index', 'admin.audit.*', 'Audit log', 'log', 0, false],
            ]],
        ];
        $mwTag = 'Admin';
    } else {
        $mwGroups = [
            [null, [
                ['dashboard', 'dashboard', 'Dashboard', 'home', 0, false],
                ['therapist.calendar', 'therapist.calendar', 'Calendar', 'calendar', 0, false],
                ['therapist.sessions.index', 'therapist.sessions.*', 'Sessions', 'clock', 0, false],
                ['messages.index', 'messages.*', 'Messages', 'chat', 'unread', false],
            ]],
            ['Clients', [
                ['therapist.clients.index', 'therapist.clients.*', 'My clients', 'clients', 0, false],
                ['therapist.reports.index', 'therapist.reports.*', 'Session notes', 'notes', 0, false],
            ]],
            ['My practice', [
                ['therapist.availability.index', 'therapist.availability.*', 'Availability', 'repeat', 0, false],
                ['therapist.stats', 'therapist.stats', 'My stats', 'chart', 0, false],
                ['therapist.profile.edit', 'therapist.profile.*', 'My profile', 'user', 0, false],
            ]],
        ];
        $mwTag = 'Therapist';
    }
    $me = auth()->user();
    $initials = collect(explode(' ', trim((string) $me?->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
@endphp

<aside class="mw-nav" id="mw-nav" aria-label="Main">
    <div class="mw-nav-brand">
        <a href="{{ route('dashboard') }}" class="mw-nav-logo">
            <span class="mark" aria-hidden="true"></span>
            <span>Mindwell</span>
        </a>
        <span class="mw-nav-tag">{{ $mwTag }}</span>
    </div>

    <nav class="mw-nav-scroll">
        @foreach ($mwGroups as [$label, $items])
            <div class="mw-nav-group">
                @if ($label)
                    <div class="mw-nav-label">{{ $label }}</div>
                @endif
                @foreach ($items as [$route, $pattern, $text, $icon, $count, $urgent])
                    @php
                        $active = request()->routeIs($pattern);
                        $isUnread = $count === 'unread';
                        $num = $isUnread ? (int) $mwUnread : (int) $count;
                    @endphp
                    <a href="{{ route($route) }}" class="mw-nav-item {{ $active ? 'is-active' : '' }}" @if ($active) aria-current="page" @endif>
                        <svg class="mw-nav-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ic[$icon] !!}</svg>
                        <span class="mw-nav-text">{{ $text }}</span>
                        @if ($isUnread)
                            <span class="mw-nav-count mw-unread-badge" style="{{ $num ? '' : 'display:none;' }}">{{ $num }}</span>
                        @elseif ($num > 0)
                            <span class="mw-nav-count {{ $urgent ? 'is-urgent' : '' }}">{{ $num > 99 ? '99+' : $num }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="mw-nav-foot">
        <span class="mw-nav-avatar" aria-hidden="true">{{ $initials ?: '•' }}</span>
        <div class="mw-nav-me">
            <div class="name">{{ $me?->name }}</div>
            <div class="role">{{ $mwTag }}</div>
        </div>
        <a href="{{ route('settings.notifications') }}" class="mw-nav-gear" title="Settings" aria-label="Settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ic['settings'] !!}</svg>
        </a>
    </div>
</aside>
<div class="mw-nav-scrim" onclick="document.body.classList.remove('mw-nav-open')"></div>
