{{-- Account tabs for clients (2026-11-01 refresh): My details / Notifications / Password,
     so the three settings pages feel like one place. Therapists don't see these. --}}
@if (auth()->user()->role === 'client')
    <style>
        .mwt-tabs { display:flex; gap:6px; flex-wrap:wrap; margin:-8px 0 22px; }
        .mwt-tabs a { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:999px; font-size:13.5px; font-weight:700; color:var(--ink-500); text-decoration:none; background:#fff; box-shadow:0 0 0 1px rgba(3,40,80,.08); transition:background .15s, color .15s; }
        .mwt-tabs a:hover { color:var(--teal-700); }
        .mwt-tabs a.on { background:var(--teal-700); color:#fff; box-shadow:none; }
    </style>
    <nav class="mwt-tabs" aria-label="Account">
        <a href="{{ route('settings.details') }}" class="{{ request()->routeIs('settings.details') ? 'on' : '' }}">My details</a>
        <a href="{{ route('settings.notifications') }}" class="{{ request()->routeIs('settings.notifications') ? 'on' : '' }}">Notifications</a>
        <a href="{{ route('password.change') }}" class="{{ request()->routeIs('password.change') ? 'on' : '' }}">Password</a>
    </nav>
@endif
