@extends('layouts.app')
@section('title', 'Users')
@section('content')
{{-- All users (2026-10-08 redesign, Doctolib-style): tabs with counts, search, one clear
     action per row and the rest under "⋯". See Admin\UserController::index(). --}}
@php
    $rolePill = ['admin' => 'teal', 'therapist' => 'warn', 'client' => 'success'];
@endphp

<div class="mw-page-head">
    <div>
        <h1>Users</h1>
        <div class="sub">Everyone with a Mindwell account — clients, therapists and the admin team.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.therapists.index') }}" class="btn ghost sm">Therapist approvals</a>
        <a href="{{ route('admin.therapists.create') }}" class="btn primary sm">Add therapist</a>
    </div>
</div>

@if (session('temp_password'))
    @php $mwTemp = session('temp_password'); @endphp
    {{-- Shown ONCE (flash). Added 2026-10-04 — see UserController::temporaryPassword(). --}}
    <div class="card" style="margin-bottom:18px;border:2px solid var(--mw-gold, #F6E1A8);">
        <div style="font-size:14px;font-weight:800;color:var(--ink-900);">Temporary password for {{ $mwTemp['name'] }}</div>
        <div style="font-size:12.5px;color:var(--ink-500);margin:4px 0 12px;">
            Give this to them in person or by phone — not by email or SMS. It's shown only this once. They'll choose their own
            password when they log in with it ({{ $mwTemp['email'] }}).
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <code id="mw-temp-pw" style="font-size:22px;font-weight:800;letter-spacing:.08em;background:#f1f5f4;padding:8px 14px;border-radius:10px;">{{ $mwTemp['password'] }}</code>
            <button type="button" class="btn ghost sm" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('mw-temp-pw').textContent).then(() => { this.textContent = 'Copied'; })">Copy</button>
        </div>
    </div>
@endif

<nav class="mw-tabs" aria-label="Filter users">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.users.index', array_filter(['tab' => $key === 'all' ? null : $key, 'q' => $q ?: null])) }}" class="{{ $tab === $key ? 'on' : '' }}">
            {{ $label }} <span class="n">{{ $counts[$key] }}</span>
        </a>
    @endforeach
</nav>

<form method="GET" action="{{ route('admin.users.index') }}" class="mw-toolbar">
    @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <label class="mw-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Search by name, email or phone" aria-label="Search users">
    </label>
    @if ($q !== '')
        <a href="{{ route('admin.users.index', array_filter(['tab' => $tab === 'all' ? null : $tab])) }}" class="btn ghost sm">Clear</a>
    @endif
    <span style="margin-left:auto;font-size:12.5px;color:var(--ink-500);">{{ $users->total() }} {{ $users->total() === 1 ? 'person' : 'people' }}</span>
</form>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Person</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th style="padding-right:18px;text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                @php $isMe = $user->id === auth()->id(); @endphp
                <tr>
                    <td style="padding-left:18px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            @include('partials.avatar', ['name' => $user->name])
                            <div style="min-width:0;">
                                <div style="font-weight:700;color:var(--ink-900);">{{ $user->name }}{{ $isMe ? ' (you)' : '' }}</div>
                                <div style="font-size:12px;color:var(--ink-500);">{{ $user->email }}{{ $user->role === 'client' && $user->clientLabel() ? ' · '.$user->clientLabel() : '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="pill {{ $rolePill[$user->role] ?? '' }}">{{ ucfirst($user->role) }}</span></td>
                    <td>
                        <span class="mw-dot {{ $user->status === 'active' ? 'ok' : ($user->status === 'suspended' ? 'bad' : 'wait') }}">{{ ucfirst($user->status ?? 'pending') }}</span>
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:var(--ink-900);">{{ $user->created_at->format('j M Y') }}</div>
                        <div style="font-size:11.5px;color:var(--ink-300);">{{ $user->created_at->diffForHumans() }}</div>
                    </td>
                    <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                        @if ($user->role === 'client' && $user->clientProfile)
                            <a href="{{ route('admin.clients.show', $user->clientProfile) }}" class="btn ghost sm">Client file</a>
                        @elseif ($user->role === 'therapist')
                            <a href="{{ route('admin.therapists.index') }}" class="btn ghost sm">Therapist</a>
                        @endif
                        @unless ($isMe)
                            <details class="mw-menu" style="vertical-align:middle;margin-left:4px;">
                                <summary aria-label="More actions for {{ $user->name }}">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                </summary>
                                <div class="pop">
                                    <form method="POST" action="{{ route('admin.users.send-reset', $user) }}">
                                        @csrf
                                        <button type="submit">Email a password reset link</button>
                                    </form>
                                    @php $mwTempConfirm = 'Set a temporary password for '.$user->name.'? Their current password will stop working, and you will need to give them the new one yourself.'; @endphp
                                    <form method="POST" action="{{ route('admin.users.temporary-password', $user) }}" onsubmit="return confirm({{ json_encode($mwTempConfirm) }});">
                                        @csrf
                                        <button type="submit">Set a temporary password</button>
                                    </form>
                                    <hr>
                                    @if ($user->status === 'suspended')
                                        <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                                            @csrf
                                            <button type="submit">Reactivate account</button>
                                        </form>
                                    @else
                                        @php $mwSuspendConfirm = 'Suspend '.$user->name.'? They won\'t be able to log in until you reactivate them.'; @endphp
                                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}" onsubmit="return confirm({{ json_encode($mwSuspendConfirm) }});">
                                            @csrf
                                            <button type="submit" class="danger">Suspend account</button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                            <div class="big">No one matches</div>
                            {{ $q !== '' ? 'Try another name or email.' : 'No users in this group yet.' }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $users->links() }}</div>

<script>
    // Close any open "⋯" menu when clicking elsewhere.
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.mw-menu[open]').forEach(function (d) { if (! d.contains(e.target)) d.removeAttribute('open'); });
    });
</script>
@endsection
