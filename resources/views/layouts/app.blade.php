<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Mindwell</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="mw-topbar">
    <a href="{{ route('dashboard') }}" class="mw-brand">
        <span class="dot"></span> Mindwell
    </a>

    @auth
        @if (auth()->user()->role === 'admin')
            <div style="font-size:12px;font-weight:700;color:var(--ink-300);">Admin console</div>
        @endif
        <div style="display:flex;align-items:center;gap:14px;">
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
                    <span class="ic"></span> Users
                </a>
                <a href="{{ route('admin.therapists.index') }}" class="side-link {{ request()->routeIs('admin.therapists.*') ? 'active' : '' }}">
                    <span class="ic"></span> Credentialing
                </a>
            </div>
            <div class="dash-main">
                @if (session('status'))
                    <div class="mw-alert success">{{ session('status') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
    @else
        {{-- Client / therapist: plain content area for now, no sidebar yet
             (therapist portal shell comes when we build that portal). --}}
        <div style="max-width:960px;margin:0 auto;padding:28px;">
            @if (session('status'))
                <div class="mw-alert success">{{ session('status') }}</div>
            @endif
            @yield('content')
        </div>
    @endif
@else
    <div style="max-width:960px;margin:0 auto;padding:28px;">
        @yield('content')
    </div>
@endauth

</body>
</html>