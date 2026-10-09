{{-- The selected event's details + actions (2026-10-08, roadmap 75). Needs: $ev (event from
     CalendarBuilder, or null), $viewer ('therapist'|'admin'), $money (ScheduleChanges::moneyDefaults
     for an admin-selected scheduled session, else null), $clashes (for a selected time off, admin). --}}
@php
    $mwCur = config('flutterwave.currency');
    $s = $ev['session'] ?? null;
    $t = $ev['timeOff'] ?? null;
    $pc = $ev['pendingChange'] ?? null;
@endphp
<div class="card" style="position:sticky;top:12px;">
    @if (! $ev)
        <div class="section-title">Details</div>
        <p style="font-size:12.5px;color:var(--ink-500);margin:0;">Click a session or time off in the calendar to see it here.</p>
    @elseif ($s)
        @php
            $client = $s->clientProfile?->user;
            $statusText = ['scheduled' => 'Confirmed', 'pending_approval' => 'Waiting for admin approval', 'completed' => 'Completed'][$s->status] ?? ucfirst($s->status);
        @endphp
        <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;">{{ $s->isInPerson() ? 'In person' : 'Video' }} session</div>
        <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
            @include('partials.avatar', ['name' => $client?->name ?? 'Client', 'size' => 38])
            <div style="min-width:0;">
                <div style="font-size:16px;font-weight:800;color:var(--ink-900);">{{ $client?->name ?? 'Client' }}</div>
                <div style="font-size:12px;color:var(--ink-500);">{{ $client?->clientLabel() }}{{ $viewer === 'admin' ? ' · with '.($s->therapistProfile?->user?->name ?? '—') : '' }}</div>
            </div>
        </div>
        <div style="font-size:13px;color:var(--ink-900);margin-top:10px;">{{ $s->scheduled_at->format('l, j M Y') }}<br>{{ $s->scheduled_at->format('g:i A') }} – {{ $s->plannedEndAt()?->format('g:i A') }}</div>
        @if ($s->isInPerson())
            <div style="font-size:12.5px;color:var(--ink-700);margin-top:6px;">{{ $s->locationText() ?? 'Place not set yet' }}</div>
        @endif
        <div style="margin-top:8px;"><span class="pill {{ $s->status === 'scheduled' ? 'success' : ($s->status === 'pending_approval' ? 'warn' : '') }}">{{ $statusText }}</span></div>

        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;">
            @if ($viewer === 'therapist')
                <a href="{{ route('therapist.clients.show', $s->client_profile_id) }}" class="btn ghost sm">Client file</a>
                @if ($s->status === 'scheduled' && ! $s->isInPerson())
                    <a href="{{ route('sessions.room', $s) }}" class="btn coral sm">Join video</a>
                @endif
                @if ($s->status === 'scheduled')
                    <a href="{{ route('therapist.sessions.close', $s) }}" class="btn ghost sm">Close out</a>
                @endif
                @if ($s->status === 'completed')
                    <a href="{{ route('therapist.reports.show', $s) }}" class="btn ghost sm">Notes</a>
                @endif
            @else
                <a href="{{ route('admin.clients.show', $s->client_profile_id) }}" class="btn ghost sm">Client file</a>
                @if ($s->status === 'pending_approval')
                    <a href="{{ route('admin.session-routing.index') }}" class="btn primary sm">Approve in Session Routing</a>
                @endif
            @endif
        </div>

        @if ($pc)
            <div class="mw-alert" style="margin-top:12px;background:var(--mw-coral-tint, #FCE1D3);color:var(--ink-900);">
                <strong>{{ $pc->requesterLabel() }} asked:</strong> {{ $pc->summary() }}
                @if ($pc->reason)<div style="font-size:12px;margin-top:2px;">“{{ $pc->reason }}”</div>@endif
                <div style="font-size:11.5px;color:var(--ink-500);margin-top:2px;">Waiting for admin · asked {{ $pc->created_at->diffForHumans() }}</div>
                @if ($viewer === 'therapist' && $pc->requester_role === 'therapist')
                    <form method="POST" action="{{ route('therapist.change-requests.withdraw', $pc) }}" style="margin-top:6px;">@csrf<button type="submit" class="btn ghost sm">Withdraw request</button></form>
                @elseif ($viewer === 'admin')
                    <a href="{{ route('admin.schedule-requests.index') }}" class="btn primary sm" style="margin-top:6px;">Decide in Requests</a>
                @endif
            </div>
        @elseif ($s->status === 'scheduled' && $s->scheduled_at->isFuture())
            @if ($viewer === 'therapist')
                {{-- Therapist asks; admin decides (Collins, 2026-10-08). --}}
                <details style="margin-top:14px;">
                    <summary class="btn ghost sm" style="list-style:none;cursor:pointer;">Ask to move</summary>
                    <form method="POST" action="{{ route('therapist.sessions.change-request', $s) }}" style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                        @csrf
                        <input type="hidden" name="type" value="reschedule">
                        <label class="mw-label" style="margin:0;">New time</label>
                        <input class="mw-field" type="datetime-local" name="proposed_at" required min="{{ now()->format('Y-m-d\TH:i') }}">
                        <input class="mw-field" name="reason" maxlength="500" placeholder="Reason (the team sees this)">
                        <button type="submit" class="btn primary sm">Send request</button>
                    </form>
                </details>
                <details style="margin-top:8px;">
                    <summary class="btn ghost sm" style="list-style:none;cursor:pointer;">Ask to cancel</summary>
                    <form method="POST" action="{{ route('therapist.sessions.change-request', $s) }}" style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                        @csrf
                        <input type="hidden" name="type" value="cancel">
                        <input class="mw-field" name="reason" maxlength="500" required placeholder="Reason (the team sees this)">
                        <button type="submit" class="btn ghost sm">Send cancel request</button>
                    </form>
                </details>
                <p style="font-size:11px;color:var(--ink-300);margin:8px 0 0;">The team approves changes and lets the client know.</p>
            @else
                {{-- Admin changes it straight away. --}}
                <details style="margin-top:14px;">
                    <summary class="btn ghost sm" style="list-style:none;cursor:pointer;">Move session</summary>
                    <form method="POST" action="{{ route('admin.sessions.reschedule', $s) }}" style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                        @csrf
                        <input class="mw-field" type="datetime-local" name="proposed_at" required min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ $s->scheduled_at->format('Y-m-d\TH:i') }}">
                        <input class="mw-field" name="reason" maxlength="500" placeholder="Reason (optional)">
                        <label style="font-size:12px;color:var(--ink-700);display:flex;gap:6px;align-items:flex-start;">
                            <input type="checkbox" name="allow_clash" value="1" style="margin-top:2px;"> Book anyway, even if the therapist isn't free then
                        </label>
                        <button type="submit" class="btn primary sm" onclick="return confirm('Move this session? The client and therapist will be told.');">Move &amp; tell them</button>
                    </form>
                </details>
                <details style="margin-top:8px;">
                    <summary class="btn ghost sm" style="list-style:none;cursor:pointer;">Cancel session</summary>
                    <form method="POST" action="{{ route('admin.sessions.cancel', $s) }}" style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                        @csrf
                        <input class="mw-field" name="reason" maxlength="500" placeholder="Reason (optional)">
                        @include('calendar._money', ['money' => $money])
                        <button type="submit" class="btn ghost sm" onclick="return confirm('Cancel this session? The client and therapist will be told.');">Cancel &amp; tell them</button>
                    </form>
                </details>
            @endif
        @endif
    @elseif ($t)
        <div style="font-size:11.5px;font-weight:700;color:var(--ink-500);text-transform:uppercase;letter-spacing:.03em;">Time off</div>
        @if ($viewer === 'admin')
            <div style="margin-top:6px;">@include('partials.person', ['name' => $t->therapistProfile?->user?->name, 'size' => 34])</div>
        @endif
        <div style="font-size:13px;color:var(--ink-900);margin-top:6px;">{{ $t->rangeLabel() }}</div>
        @if ($t->reason)<div style="font-size:12.5px;color:var(--ink-700);margin-top:4px;">“{{ $t->reason }}”</div>@endif
        <div style="margin-top:8px;"><span class="pill {{ $t->status === 'approved' ? 'success' : 'warn' }}">{{ $t->status === 'approved' ? 'Approved' : 'Waiting for approval' }}</span></div>

        @if ($viewer === 'therapist' && ($t->status === 'pending' || $t->ends_at->isFuture()))
            <form method="POST" action="{{ route('therapist.time-off.cancel', $t) }}" style="margin-top:12px;" onsubmit="return confirm('Remove this time off?');">
                @csrf
                <button type="submit" class="btn ghost sm">{{ $t->status === 'pending' ? 'Withdraw request' : 'Remove time off' }}</button>
            </form>
        @elseif ($viewer === 'admin' && $t->status === 'pending')
            @include('calendar._timeoff-decide', ['t' => $t, 'clashes' => $clashes])
        @endif
    @endif
</div>
