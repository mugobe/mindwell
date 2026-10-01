@extends('layouts.app')
@section('title', 'Messages')
@section('content')
{{--
    Messages inbox, added 2026-09-29 — see MessageController::index(). Lists everyone
    this person may message (they've had or booked a session together), newest
    conversation first, including people they haven't written to yet.
--}}
<style>
    .mw-inbox { max-width: 760px; margin: 0 auto; }
    .mw-thread { display:flex; align-items:center; gap:14px; padding:14px 18px; text-decoration:none; color:inherit; border-bottom:1px solid rgba(0,0,0,.05); transition:background .12s; }
    .mw-thread:last-child { border-bottom:none; }
    .mw-thread:hover { background:#fbf8f2; }
    .mw-thread-avatar { flex-shrink:0; width:44px; height:44px; border-radius:50%; background:var(--mw-teal-tint, #DCEDE8); color:var(--teal-600, #2f6f66); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:17px; }
    .mw-thread-main { flex:1; min-width:0; }
    .mw-thread-name { font-size:14px; font-weight:700; color:var(--ink-900); }
    .mw-thread-preview { font-size:12.5px; color:var(--ink-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
    .mw-thread.unread .mw-thread-name, .mw-thread.unread .mw-thread-preview { color:var(--ink-900); font-weight:700; }
    .mw-thread-meta { flex-shrink:0; text-align:right; font-size:11.5px; color:var(--ink-300); display:flex; flex-direction:column; align-items:flex-end; gap:5px; }
    .mw-count { min-width:20px; height:20px; padding:0 6px; border-radius:10px; background:var(--coral-500, #e8825e); color:#fff; font-size:11px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
</style>

<div class="mw-inbox">
    <div class="dash-head" style="align-items:center;">
        <div style="display:flex;align-items:center;gap:12px;">
            @include('partials.mw-icon-badge', ['size' => 40, 'margin' => '0', 'tint' => 'var(--mw-teal-tint, #DCEDE8)', 'icon' => '<path d="M4 5.5h16a1 1 0 011 1v9a1 1 0 01-1 1H10l-4.5 3.5V16.5H4a1 1 0 01-1-1v-9a1 1 0 011-1z"/><path d="M8 10h8M8 13h5"/>'])
            <h1>Messages</h1>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="card" style="text-align:center;padding:34px 24px;">
            @include('partials.mw-illustration', ['variant' => $role === 'client' ? 'compass' : 'clipboard', 'size' => 110])
            <div style="font-size:15px;font-weight:800;color:var(--ink-900);margin-top:8px;">No conversations yet</div>
            <p style="font-size:13px;color:var(--ink-500);margin:6px auto 0;max-width:420px;">
                @if ($role === 'client')
                    Once you've booked a session with a therapist, you'll be able to message them here.
                @else
                    Clients you've had or booked a session with will appear here.
                @endif
            </p>
        </div>
    @else
        <div class="card" style="padding:0;overflow:hidden;">
            @foreach ($rows as $row)
                <a href="{{ $row['conversation'] ? route('messages.show', $row['conversation']) : route('messages.start', $row['profile_id']) }}"
                   class="mw-thread {{ $row['unread'] > 0 ? 'unread' : '' }}">
                    <div class="mw-thread-avatar">{{ mb_strtoupper(mb_substr($row['name'], 0, 1)) }}</div>
                    <div class="mw-thread-main">
                        <div class="mw-thread-name">{{ $row['name'] }}</div>
                        <div class="mw-thread-preview">
                            {{ $row['preview'] ? \Illuminate\Support\Str::limit($row['preview'], 90) : 'No messages yet — say hello' }}
                        </div>
                    </div>
                    <div class="mw-thread-meta">
                        @if ($row['last_at'])
                            <span>{{ $row['last_at']->isToday() ? $row['last_at']->format('g:i A') : $row['last_at']->format('M j') }}</span>
                        @endif
                        @if ($row['unread'] > 0)
                            <span class="mw-count">{{ $row['unread'] }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <p style="font-size:11px;color:var(--ink-300);text-align:center;margin-top:14px;">
        Messages are private between you and {{ $role === 'client' ? 'your therapist' : 'each client' }}.
        @if ($role === 'client')
            They aren't watched around the clock — if you're in crisis, <a href="{{ route('intake.crisis') }}" style="color:var(--teal-600);">get help now</a>.
        @endif
    </p>
</div>
@endsection
