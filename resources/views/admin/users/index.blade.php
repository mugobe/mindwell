@extends('layouts.app')
@section('title', 'Users')
@section('content')

<div class="dash-head">
    <h1>All users</h1>
    <a href="{{ route('admin.therapists.index') }}" class="btn ghost sm">Therapist approvals</a>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th style="padding-right:16px;">Joined</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
            <tr>
                <td style="padding-left:16px;display:flex;align-items:center;gap:10px;">
                    <div class="avatar" style="width:30px;height:30px;font-size:11px;">{{ collect(explode(' ', $user->name))->map(fn($p) => $p[0] ?? '')->join('') }}</div>
                    {{ $user->name }}
                </td>
                <td>{{ $user->email }}</td>
                <td>
                    <span class="pill {{ $user->role === 'admin' ? 'teal' : ($user->role === 'therapist' ? 'warn' : 'success') }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td class="capitalize">{{ $user->status }}</td>
                <td style="padding-right:16px;">{{ $user->created_at->format('M j, Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">No users yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $users->links() }}</div>
@endsection