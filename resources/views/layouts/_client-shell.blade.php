{{-- Client shell (2026-11-01 refresh). Sidebar on computers, top bar + bottom tabs on
     phones. Main sections: Home, Sessions, Messages, Journal, Progress. Account pages sit
     in their own group (sidebar) or in the account menu (phone). "Get help now" is always
     one tap away. Needs: $mwUnread. Wraps the page: @yield('content') is in mwc-wrap. --}}
@php
    $mwcIc = [
        'home' => '<path d="M4 11l8-7 8 7"/><path d="M6 10v10h4v-6h4v6h4V10"/>',
        'sessions' => '<rect x="4" y="5" width="16" height="15" rx="2.5"/><path d="M4 9.5h16"/><path d="M8.5 3v4M15.5 3v4"/><path d="M8 13.5h3M8 16.5h6"/>',
        'chat' => '<path d="M4 5.5h16a1 1 0 011 1v9a1 1 0 01-1 1H10l-4.5 3.5V16.5H4a1 1 0 01-1-1v-9a1 1 0 011-1z"/><path d="M8 10h8M8 13h5"/>',
        'journal' => '<path d="M6 3.5h11a1.5 1.5 0 011.5 1.5v15H7a1.5 1.5 0 01-1.5-1.5V4A.5.5 0 016 3.5z"/><path d="M5.5 18.5A1.5 1.5 0 017 17h11.5"/><path d="M9.5 8h5M9.5 11h3"/>',
        'progress' => '<path d="M4 19h16"/><path d="M5 15l4-4 3 3 6-6"/><path d="M15 8h3v3"/>',
        'plan' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>',
        'card' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>',
        'building' => '<rect x="4" y="3" width="9" height="18" rx="1"/><rect x="14" y="9" width="6" height="12" rx="1"/><path d="M7.5 7h2M7.5 11h2M7.5 15h2"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/>',
        'bell' => '<path d="M6 9a6 6 0 0112 0c0 5 2 6.5 2 6.5H4S6 14 6 9z"/><path d="M10 19a2 2 0 004 0"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
        'logout' => '<path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3"/><path d="M10 16l-4-4 4-4"/><path d="M6 12h10"/>',
        'therapy' => '<path d="M12 20s-7-4.4-7-10a4 4 0 017-2.6A4 4 0 0119 10c0 5.6-7 10-7 10z"/><path d="M9 11h6M12 8v6"/>',
        'library' => '<path d="M12 6.5C10 5 7 4.5 4 5v13c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5V5c-3-.5-6 0-8 1.5z"/><path d="M12 6.5v13"/>',
        'help' => '<path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0112 7.6a4.3 4.3 0 017.5 2.7c0 5.6-7.5 10.2-7.5 10.2z"/>',
    ];
    $svg = fn ($k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$mwcIc[$k].'</svg>';

    // 2026-11-07: My therapy and Resources added. Phones keep five bottom tabs (the
    // fifth value): Progress and Resources sit in the account menu there.
    $mwcMain = [
        ['Home', 'dashboard', 'home', ['dashboard'], true],
        ['My therapy', 'care.index', 'therapy', ['care.*'], true],
        ['Sessions', 'sessions.history', 'sessions', ['sessions.*', 'therapists.*', 'intake.*'], true],
        ['Messages', 'messages.index', 'chat', ['messages.*'], true],
        ['Journal', 'journal.index', 'journal', ['journal.*'], true],
        ['Progress', 'progress.index', 'progress', ['progress.*'], false],
        ['Resources', 'resources.index', 'library', ['resources.*'], false],
    ];
    $mwcAccount = [
        ['My plan', 'subscription.index', 'plan', ['subscription.*']],
        ['Payments', 'payments.index', 'card', ['payments.*']],
        ['Employer benefit', 'employer-seat.index', 'building', ['employer-seat.*']],
        ['My details', 'settings.details', 'user', ['settings.details', 'about.*']],
        ['Notifications', 'settings.notifications', 'bell', ['settings.notifications']],
        ['Password', 'password.change', 'lock', ['password.change']],
    ];
    $mwcOn = fn (array $patterns) => request()->routeIs(...$patterns);
    // Focus pages (video room, lobby): no bottom tabs on phones and no entrance animation.
    $mwcFocus = request()->routeIs('sessions.room', 'sessions.lobby');
    $mwcName = auth()->user()->name;
    $mwcInitial = mb_strtoupper(mb_substr($mwcName, 0, 1));
@endphp

<div class="mwc {{ $mwcFocus ? 'is-focus' : '' }}">
    {{-- Computer: sidebar --}}
    <aside class="mwc-side" aria-label="Main menu">
        <a href="{{ route('dashboard') }}" class="mwc-logo" aria-label="Mindwell home">
            <img src="{{ asset('images/mindwell-logo-horizontal.png') }}" alt="Mindwell Consultancy Ltd">
        </a>
        <nav class="mwc-navlist">
            @foreach ($mwcMain as [$label, $route, $icon, $patterns])
                <a href="{{ route($route) }}" class="mwc-item {{ $mwcOn($patterns) ? 'is-on' : '' }}" @if ($mwcOn($patterns)) aria-current="page" @endif>
                    {!! $svg($icon) !!}<span>{{ $label }}</span>
                    @if ($icon === 'chat')
                        <span class="mwc-count mw-unread-badge" style="{{ $mwUnread ? '' : 'display:none;' }}">{{ $mwUnread }}</span>
                    @endif
                </a>
            @endforeach
            <div class="mwc-label">Account</div>
            @foreach ($mwcAccount as [$label, $route, $icon, $patterns])
                <a href="{{ route($route) }}" class="mwc-item {{ $mwcOn($patterns) ? 'is-on' : '' }}" @if ($mwcOn($patterns)) aria-current="page" @endif>
                    {!! $svg($icon) !!}<span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <a href="{{ route('urgent-help') }}" class="mwc-help">
            {!! $svg('help') !!}
            <span><b>Get help now</b>Crisis numbers, any time</span>
        </a>
        <div class="mwc-me">
            <div class="mwc-avatar" aria-hidden="true">{{ $mwcInitial }}</div>
            <div class="who"><div class="nm">{{ $mwcName }}</div><div class="sb">Client</div></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="mwc-iconbtn" title="Log out" aria-label="Log out">{!! $svg('logout') !!}</button>
            </form>
        </div>
    </aside>

    <div class="mwc-main">
        {{-- Phone: top bar --}}
        <header class="mwc-top">
            <a href="{{ route('dashboard') }}" class="mwc-logo" aria-label="Mindwell home">
                <img src="{{ asset('images/mindwell-logo-horizontal.png') }}" alt="Mindwell">
            </a>
            <a href="{{ route('urgent-help') }}" class="mwc-top-help">{!! $svg('help') !!}Get help</a>
            <details class="mwc-menu">
                <summary aria-label="Account menu"><span class="mwc-avatar">{{ $mwcInitial }}</span></summary>
                <div class="pop">
                    <div style="padding:8px 12px 10px;">
                        <div style="font-weight:800;font-size:14px;color:var(--ink-900);">{{ $mwcName }}</div>
                        <div style="font-size:12px;color:var(--ink-500);">{{ auth()->user()->email }}</div>
                    </div>
                    <hr>
                    @foreach ($mwcMain as [$label, $route, $icon, $patterns, $tab])
                        @unless ($tab)<a href="{{ route($route) }}">{!! $svg($icon) !!}{{ $label }}</a>@endunless
                    @endforeach
                    <hr>
                    @foreach ($mwcAccount as [$label, $route, $icon])
                        <a href="{{ route($route) }}">{!! $svg($icon) !!}{{ $label }}</a>
                    @endforeach
                    <hr>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">{!! $svg('logout') !!}Log out</button>
                    </form>
                </div>
            </details>
        </header>

        <main class="mwc-wrap" id="mwc-content">
            @if (session('status'))
                <div class="mw-alert success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mw-alert danger">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>

    {{-- Phone: bottom tabs --}}
    <nav class="mwc-tabs" aria-label="Main menu">
        @foreach ($mwcMain as [$label, $route, $icon, $patterns, $tab])
            @continue(! $tab)
            <a href="{{ route($route) }}" class="mwc-tab {{ $mwcOn($patterns) ? 'is-on' : '' }}" @if ($mwcOn($patterns)) aria-current="page" @endif>
                <span class="pillbg">{!! $svg($icon) !!}</span>{{ $label === 'My therapy' ? 'Therapy' : $label }}
                @if ($icon === 'chat')
                    <span class="mwc-count mw-unread-badge" style="{{ $mwUnread ? '' : 'display:none;' }}">{{ $mwUnread }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</div>

<script>
    // Close the phone account menu when tapping elsewhere.
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.mwc-menu[open]').forEach(function (m) { if (!m.contains(e.target)) m.removeAttribute('open'); });
    });
</script>
