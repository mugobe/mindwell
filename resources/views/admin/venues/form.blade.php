@extends('layouts.app')
@section('title', $venue->exists ? 'Edit venue' : 'Add venue')
@section('content')

<div class="dash-head">
    <h1>{{ $venue->exists ? 'Edit '.$venue->name : 'Add a venue' }}</h1>
    <a href="{{ route('admin.venues.index') }}" class="btn ghost sm">All venues</a>
</div>

<div class="card" style="max-width:560px;">
    @if ($errors->any())
        <div class="mw-alert danger">
            <ul style="margin:0;padding-left:16px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $venue->exists ? route('admin.venues.update', $venue) : route('admin.venues.store') }}" style="display:flex;flex-direction:column;gap:16px;">
        @csrf
        @if ($venue->exists)
            @method('PUT')
        @endif

        <div>
            <label class="mw-label" for="name">Name</label>
            <input class="mw-field" id="name" name="name" value="{{ old('name', $venue->name) }}" required placeholder="e.g. Mindwell Counselling Rooms, Kololo">
        </div>
        <div>
            <label class="mw-label" for="address">Address</label>
            <input class="mw-field" id="address" name="address" value="{{ old('address', $venue->address) }}" placeholder="Plot, street, area">
        </div>
        <div>
            <label class="mw-label" for="directions">Directions for clients</label>
            <textarea class="mw-field" id="directions" name="directions" rows="3" placeholder="e.g. Second floor, ask for Mindwell at reception. Parking at the back.">{{ old('directions', $venue->directions) }}</textarea>
        </div>
        <div>
            <label class="mw-label" for="map_url">Map link (optional)</label>
            <input class="mw-field" type="url" id="map_url" name="map_url" value="{{ old('map_url', $venue->map_url) }}" placeholder="https://maps.google.com/…">
        </div>
        <div>
            <label class="mw-label" for="phone">Reception phone (optional)</label>
            <input class="mw-field" id="phone" name="phone" value="{{ old('phone', $venue->phone) }}">
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-700);">
            <input type="checkbox" name="is_active" value="1" {{ old('_token') ? (old('is_active') ? 'checked' : '') : ($venue->is_active ? 'checked' : '') }}>
            In use (shown when approving in-person bookings)
        </label>

        <button type="submit" class="btn primary block">{{ $venue->exists ? 'Save venue' : 'Add venue' }}</button>
    </form>
</div>
@endsection
