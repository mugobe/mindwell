@extends('layouts.app')
@section('title', 'Messages')
@section('content')
{{--
    Messages inbox, added 2026-09-29 (see MessageController::index()). Lists everyone this
    person may message, newest conversation first. 2026-11-01: two panes on wide screens;
    the right pane invites the person to pick a conversation.
--}}
@include('messages._inbox-style')

@if ($rows->isEmpty())
    <div class="dash-head">
        <h1>Messages</h1>
    </div>
    <div class="card" style="text-align:center;padding:44px 24px;">
        @include('partials.mw-illustration', ['variant' => $role === 'client' ? 'compass' : 'clipboard', 'size' => 120])
        <div style="font-size:17px;font-weight:800;color:var(--ink-900);margin-top:10px;">No conversations yet</div>
        <p style="font-size:13.5px;color:var(--ink-500);margin:6px auto 0;max-width:420px;">
            @if ($role === 'client')
                Once you book a session with a therapist, you can message them here.
            @else
                Clients you have had or booked a session with will appear here.
            @endif
        </p>
        @if ($role === 'client')
            <a href="{{ route('therapists.browse') }}" class="btn coral" style="display:inline-block;width:auto;border-radius:999px;padding:11px 24px;margin-top:16px;">Find a therapist</a>
        @endif
    </div>
@else
    <div class="mw-panes is-inbox">
        <section class="mw-pane list">
            <div class="mw-pane-head">Messages</div>
            <div class="mw-pane-list">
                @include('messages._inbox-list', ['rows' => $rows])
            </div>
            <div class="mw-pane-foot">
                Messages are private between you and {{ $role === 'client' ? 'your therapist' : 'each client' }}.
                @if ($role === 'client')
                    They are not checked around the clock. If you are in crisis, <a href="{{ route('urgent-help') }}" style="color:var(--teal-600);font-weight:700;">get help now</a>.
                @endif
            </div>
        </section>
        <section class="mw-pane chat">
            <div class="mw-pane-empty">
                @include('partials.mw-illustration', ['variant' => 'sprout', 'size' => 130])
                <div style="font-size:17px;font-weight:800;color:var(--ink-900);margin-top:10px;">Choose a conversation</div>
                <div style="margin-top:4px;max-width:320px;">Pick someone on the left to read and send messages.</div>
            </div>
        </section>
    </div>
@endif
@endsection
