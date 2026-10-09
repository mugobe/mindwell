@extends('layouts.app')
@section('title', $employer->company_name)
@section('content')

{{-- 2026-10-08 redesign: page head, KPI tiles, seat code chips, seat actions under ⋯. --}}
<div class="mw-page-head">
    <div style="display:flex;align-items:center;gap:14px;">
        @include('partials.avatar', ['name' => $employer->company_name, 'size' => 46])
        <div>
            <h1>{{ $employer->company_name }}</h1>
            <div class="sub">
                <span class="mw-dot {{ $employer->status === 'active' ? 'ok' : 'bad' }}">{{ ucfirst($employer->status) }}</span>
                @if ($employer->contract_renewal_date) · renews {{ $employer->contract_renewal_date->format('j M Y') }}@endif
                @if ($employer->contact_name) · {{ $employer->contact_name }}@endif
                @if ($employer->contact_email) ({{ $employer->contact_email }})@endif
            </div>
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.employers.index') }}" class="btn ghost sm">All employers</a>
        @if ($employer->status === 'active')
            @php
                // json_encode() (rather than hand-building a single-quoted JS string) so a
                // company name containing an apostrophe or quote can't break this attribute.
                $mwDeactivateEmployerMsg = "Deactivate {$employer->company_name}? None of its seats will cover a session until it's reactivated.";
            @endphp
            <form method="POST" action="{{ route('admin.employers.deactivate', $employer) }}" onsubmit="return confirm({{ json_encode($mwDeactivateEmployerMsg) }});" style="display:inline;">
                @csrf
                <button type="submit" class="btn ghost sm" style="color:#C0392B;">Deactivate employer</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.employers.activate', $employer) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn primary sm">Reactivate employer</button>
            </form>
        @endif
    </div>
</div>

@if ($employer->isEndingSoon())
    <div class="mw-alert" style="background:#FDF1EC;color:#8a4326;border:1px solid rgba(232,130,94,.35);margin-bottom:14px;">
        The contract renews on <strong>{{ $employer->contract_renewal_date->format('j M Y') }}</strong> — contact them about renewing, then update the date.
    </div>
@endif

<div class="mw-kpis">
    <div class="mw-kpi"><div class="k">Seats purchased</div><div class="v">{{ $employer->seats_purchased }}</div><div class="d">{{ $employer->seats->count() }} codes generated</div></div>
    <div class="mw-kpi"><div class="k">Redeemed</div><div class="v">{{ $employer->activeSeatsCount() }}</div><div class="d">Staff using a seat now</div></div>
    <div class="mw-kpi"><div class="k">Sessions had</div><div class="v">{{ $totalSessionsHad }}</div><div class="d">Covered by this employer</div></div>
</div>

<div class="card" style="padding:0;">
    <div class="mw-card-head"><h2>Seats</h2></div>
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Seat code</th>
                <th>Assigned to</th>
                <th>Assigned at</th>
                <th>Sessions had</th>
                <th>Status</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employer->seats as $seat)
            <tr>
                <td style="padding-left:18px;"><span class="mw-code">{{ $seat->seat_code }}</span></td>
                <td>@if ($seat->clientProfile?->user) @include('partials.person', ['name' => $seat->clientProfile->user->name, 'size' => 28]) @else <span style="color:var(--ink-300);">— unredeemed —</span> @endif</td>
                <td style="white-space:nowrap;">{{ $seat->assigned_at?->format('j M Y') ?? '—' }}</td>
                <td>{{ $seat->sessions_had ?? '—' }}</td>
                <td>
                    <span class="mw-dot {{ $seat->is_active ? 'ok' : 'bad' }}">{{ $seat->is_active ? 'Active' : 'Switched off' }}</span>
                </td>
                <td style="padding-right:18px;text-align:right;white-space:nowrap;">
                    @php
                        // Same json_encode() safe-embedding as the "Deactivate employer" button
                        // above -- a client's name could contain an apostrophe or quote too.
                        $mwReissueMsg = $seat->client_profile_id
                            ? 'Free up this seat for someone new? '.($seat->clientProfile?->user?->name ?? 'The current holder')."'s old code will stop working."
                            : 'Generate a fresh code for this seat? The current code will stop working.';
                    @endphp
                    <details class="mw-menu">
                        <summary aria-label="Seat actions">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                        </summary>
                        <div class="pop">
                            <button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText({{ json_encode($seat->seat_code) }}).then(() => { this.textContent = 'Copied'; })">Copy code</button>
                            <form method="POST" action="{{ route('admin.employers.seats.reissue', [$employer, $seat]) }}" onsubmit="return confirm({{ json_encode($mwReissueMsg) }});">
                                @csrf
                                <button type="submit">Reissue (new code)</button>
                            </form>
                            <hr>
                            @if ($seat->is_active)
                                <form method="POST" action="{{ route('admin.employers.seats.deactivate', [$employer, $seat]) }}">
                                    @csrf
                                    <button type="submit" class="danger">Switch off this seat</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.employers.seats.activate', [$employer, $seat]) }}">
                                    @csrf
                                    <button type="submit">Switch this seat back on</button>
                                </form>
                            @endif
                        </div>
                    </details>
                </td>
            </tr>
            @empty
            <tr><td colspan="6"><div class="mw-empty"><div class="big">No seats generated</div></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;line-height:1.6;">
    A client redeems one of these codes from their "Employer benefit" page; their sessions are then free while this employer is Active
    and its contract hasn't lapsed. "Sessions had" counts only sessions this seat actually covered. "Deactivate employer" cuts off every
    seat at once; switching off one seat cuts off only that person. "Reissue" clears a seat and makes a new code — use it when someone
    leaves and the seat should go to a new hire. Reissuing doesn't notify anyone of the new code yet.
</p>

<script>
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.mw-menu[open]').forEach(function (d) { if (! d.contains(e.target)) d.removeAttribute('open'); });
    });
</script>
@endsection
