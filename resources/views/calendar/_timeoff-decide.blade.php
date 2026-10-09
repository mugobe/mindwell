{{-- Approve / decline a time off request (2026-10-08). Needs $t (TimeOff) and $clashes (its
     clashing sessions, or null to skip the list). --}}
@if ($clashes && $clashes->isNotEmpty())
    <div class="mw-alert" style="margin-top:10px;background:var(--mw-coral-tint, #FCE1D3);color:var(--ink-900);font-size:12px;">
        <strong>{{ $clashes->count() }} {{ $clashes->count() === 1 ? 'session falls' : 'sessions fall' }} inside this time off.</strong>
        Approving doesn't move them — move or cancel them from the calendar.
        @foreach ($clashes as $c)
            <div style="margin-top:2px;">· {{ $c->scheduled_at->format('D j M, g:i A') }} — {{ $c->clientProfile?->user?->name ?? 'Client' }}{{ $c->status === 'pending_approval' ? ' (waiting approval)' : '' }}</div>
        @endforeach
    </div>
@endif
<form method="POST" action="{{ route('admin.time-off.decide', $t) }}" style="display:flex;flex-direction:column;gap:8px;margin-top:10px;">
    @csrf
    <input class="mw-field" name="note" maxlength="500" placeholder="Note to the therapist (optional)" style="font-size:12.5px;">
    <div style="display:flex;gap:6px;">
        <button type="submit" name="approve" value="1" class="btn primary sm">Approve</button>
        <button type="submit" name="approve" value="0" class="btn ghost sm">Decline</button>
    </div>
</form>
