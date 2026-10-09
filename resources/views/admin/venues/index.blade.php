@extends('layouts.app')
@section('title', 'Venues')
@section('content')
{{-- Admin → Venues (2026-10-07, roadmap 68): where in-person sessions happen.
     2026-10-08 redesign: page head, place icons, status dots, empty state. --}}
@php
    $pin = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0119 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';
@endphp

<div class="mw-page-head">
    <div>
        <h1>Venues</h1>
        <div class="sub">Places where Mindwell sees clients in person. You pick one when approving an in-person booking.</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.therapists.index') }}" class="btn ghost sm">Therapists</a>
        <a href="{{ route('admin.venues.create') }}" class="btn primary sm">Add a venue</a>
    </div>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Venue</th>
                <th>Phone</th>
                <th>Upcoming sessions</th>
                <th>Status</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($venues as $venue)
                <tr>
                    <td style="padding-left:18px;">
                        <div class="mw-person">
                            <span class="mw-av {{ $venue->is_active ? 'c0' : '' }}" style="{{ $venue->is_active ? '' : 'background:#c9d2cf;' }}">{!! $pin !!}</span>
                            <div class="mw-person-t">
                                <a href="{{ route('admin.venues.edit', $venue) }}" class="nm">{{ $venue->name }}</a>
                                <div class="sb">{{ $venue->address ?? 'No address yet' }}
                                    @if ($venue->map_url) · <a href="{{ $venue->map_url }}" target="_blank" rel="noopener" style="color:var(--teal-600,#2f6f66);font-weight:700;">Map ↗</a>@endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12.5px;white-space:nowrap;">{{ $venue->phone ?? '—' }}</td>
                    <td>
                        @if ($venue->sessions_count)
                            <strong>{{ $venue->sessions_count }}</strong> <span style="font-size:12px;color:var(--ink-500);">booked</span>
                        @else
                            <span style="color:var(--ink-300);">None</span>
                        @endif
                    </td>
                    <td><span class="mw-dot {{ $venue->is_active ? 'ok' : '' }}">{{ $venue->is_active ? 'In use' : 'Not in use' }}</span></td>
                    <td style="padding-right:18px;text-align:right;"><a href="{{ route('admin.venues.edit', $venue) }}" class="btn ghost sm">Edit</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="mw-empty">
                            {!! str_replace('width="16" height="16"', '', $pin) !!}
                            <div class="big">No venues yet</div>
                            Add the places Mindwell sees clients in person.
                            <div style="margin-top:10px;"><a href="{{ route('admin.venues.create') }}" class="btn primary sm">Add a venue</a></div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    The client and therapist get the venue's name, address, directions and map link in their confirmation email.
    Switch on in-person bookings per therapist under Therapists.
</p>
@endsection
