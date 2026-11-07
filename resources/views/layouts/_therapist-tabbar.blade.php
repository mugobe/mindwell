{{-- Therapist bottom tabs on phones (2026-11-03), the same pattern as the client web and
     app. The full menu is still one tap away behind the menu button. Needs $mwUnread. --}}
@php
    $mwTabs = [
        ['dashboard', 'dashboard', 'Today', '<path d="M4 11l8-7 8 7"/><path d="M6 10v10h12V10"/>'],
        ['therapist.calendar', 'therapist.calendar', 'Calendar', '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/>'],
        ['therapist.sessions.index', 'therapist.sessions.*', 'Sessions', '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>'],
        ['messages.index', 'messages.*', 'Messages', '<path d="M4 5.5h16a1 1 0 011 1v9a1 1 0 01-1 1H10l-4.5 3.5V16.5H4a1 1 0 01-1-1v-9a1 1 0 011-1z"/>'],
        ['therapist.clients.index', 'therapist.clients.*', 'Clients', '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c1-3.3 3.4-5 6-5s5 1.7 6 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.6c2.2.2 3.9 1.7 5 4.4"/>'],
    ];
@endphp
<nav class="mw-tabbar" aria-label="Quick links">
    @foreach ($mwTabs as [$route, $pattern, $label, $icon])
        <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'on' : '' }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
            {{ $label }}
            @if ($route === 'messages.index')
                <span class="cnt mw-unread-badge" style="{{ $mwUnread ? '' : 'display:none;' }}">{{ $mwUnread }}</span>
            @endif
        </a>
    @endforeach
</nav>
