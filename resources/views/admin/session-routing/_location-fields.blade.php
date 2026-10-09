{{-- Where an in-person session happens (2026-10-07): a venue, extra details, or both. --}}
@php
    // Keep the session's current venue in the list even if it's since been switched off,
    // so editing just the room details doesn't silently drop it.
    $mwVenueList = ($session->venue_id && ! $venues->contains('id', $session->venue_id) && $session->venue)
        ? $venues->concat([$session->venue]) : $venues;
@endphp
<select name="venue_id" class="mw-field" style="font-size:12.5px;">
    <option value="">Pick a venue…</option>
    @foreach ($mwVenueList as $venue)
        <option value="{{ $venue->id }}" {{ $session->venue_id === $venue->id ? 'selected' : '' }}>{{ $venue->name }}</option>
    @endforeach
</select>
<textarea name="location_details" class="mw-field" rows="2" maxlength="1000" style="font-size:12.5px;" placeholder="Room / floor, or another place if not a venue above">{{ $session->location_details }}</textarea>
@if ($venues->isEmpty())
    <div style="font-size:11px;color:var(--ink-300);">No venues yet — type the place, or <a href="{{ route('admin.venues.create') }}">add a venue</a>.</div>
@endif
