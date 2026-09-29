@extends('layouts.app')
@section('title', $employer->company_name)
@section('content')

<div class="dash-head">
    <h1>{{ $employer->company_name }}</h1>
    <div style="display:flex;gap:8px;">
        @if ($employer->status === 'active')
            @php
                // json_encode() (rather than hand-building a single-quoted JS string) so a
                // company name containing an apostrophe or quote can't break this attribute
                // or the confirm() call -- {{ }} HTML-escapes the JSON's own double quotes,
                // which the browser correctly decodes back before the JS ever sees them.
                $mwDeactivateEmployerMsg = "Deactivate {$employer->company_name}? None of its seats will cover a session until it's reactivated.";
            @endphp
            <form method="POST" action="{{ route('admin.employers.deactivate', $employer) }}" onsubmit="return confirm({{ json_encode($mwDeactivateEmployerMsg) }});">
                @csrf
                <button type="submit" class="btn ghost sm" style="color:var(--danger,#c0392b);">Deactivate employer</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.employers.activate', $employer) }}">
                @csrf
                <button type="submit" class="btn primary sm">Reactivate employer</button>
            </form>
        @endif
        <a href="{{ route('admin.employers.index') }}" class="btn ghost sm">Back to employers</a>
    </div>
</div>

<div class="stat-row" style="margin-bottom:24px;">
    <div class="stat-tile">
        <div class="label">Seats purchased</div>
        <div class="value">{{ $employer->seats_purchased }}</div>
    </div>
    <div class="stat-tile">
        <div class="label">Redeemed</div>
        <div class="value">{{ $employer->activeSeatsCount() }}</div>
    </div>
    <div class="stat-tile">
        <div class="label">Sessions had</div>
        <div class="value">{{ $totalSessionsHad }}</div>
    </div>
    <div class="stat-tile">
        <div class="label">Status</div>
        <div class="value" style="font-size:16px;">
            <span class="pill {{ $employer->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($employer->status) }}</span>
        </div>
    </div>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Seat code</th>
                <th>Assigned to</th>
                <th>Assigned at</th>
                <th>Sessions had</th>
                <th>Active</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employer->seats as $seat)
            <tr>
                <td style="padding-left:16px;font-family:monospace;font-size:12.5px;">{{ $seat->seat_code }}</td>
                <td>{{ $seat->clientProfile?->user?->name ?? '— unredeemed —' }}</td>
                <td>{{ $seat->assigned_at?->format('M j, Y') ?? '—' }}</td>
                <td>{{ $seat->sessions_had ?? '—' }}</td>
                <td>
                    <span class="pill {{ $seat->is_active ? 'success' : 'danger' }}">{{ $seat->is_active ? 'Yes' : 'No' }}</span>
                </td>
                <td style="padding-right:16px;text-align:right;white-space:nowrap;">
                    @if ($seat->is_active)
                        <form method="POST" action="{{ route('admin.employers.seats.deactivate', [$employer, $seat]) }}" style="display:inline-block;">
                            @csrf
                            <button type="submit" class="btn ghost sm">Deactivate</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.employers.seats.activate', [$employer, $seat]) }}" style="display:inline-block;">
                            @csrf
                            <button type="submit" class="btn ghost sm">Reactivate</button>
                        </form>
                    @endif
                    @php
                        // Same json_encode() safe-embedding as the "Deactivate employer" button
                        // above -- a client's name could contain an apostrophe or quote too.
                        $mwReissueMsg = $seat->client_profile_id
                            ? 'Free up this seat for someone new? '.($seat->clientProfile?->user?->name ?? 'The current holder')."'s old code will stop working."
                            : 'Generate a fresh code for this seat? The current code will stop working.';
                    @endphp
                    <form method="POST" action="{{ route('admin.employers.seats.reissue', [$employer, $seat]) }}" style="display:inline-block;margin-left:6px;" onsubmit="return confirm({{ json_encode($mwReissueMsg) }});">
                        @csrf
                        <button type="submit" class="btn ghost sm">Reissue</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:16px;color:var(--ink-500);">No seats generated.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    A client redeems one of these codes from their "Employer benefit" page. Once redeemed, "Assigned to" and
    "Assigned at" fill in above and their sessions are free for as long as this employer's status stays Active
    and its contract hasn't lapsed. "Sessions had" counts only sessions actually covered by this seat (booked
    while it was active, with nothing paid or spent from a credit) — a session that client took before redeeming,
    or after this benefit lapsed, won't be counted here.
    <br><br>
    "Deactivate employer" above cuts off every seat at once, immediately. Per-seat "Deactivate" only cuts off that
    one person, leaving their history in place — use "Reactivate" to undo either one. "Reissue" clears a seat and
    generates a brand-new code, so an old or leaked code stops working — use it when someone leaves the company
    and the seat should go to a new hire instead. Still not built: reissuing doesn't currently notify anyone of
    the new code, and there's no automatic warning as a contract's renewal date approaches — both worth a future
    round if they matter.
</p>
@endsection