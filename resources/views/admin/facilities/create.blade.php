@extends('layouts.app')
@section('title', 'Add Facility')
@section('content')

<div class="dash-head">
    <h1>Add a facility</h1>
    <a href="{{ route('admin.facilities.index') }}" class="btn ghost sm">Back to facilities</a>
</div>

<div class="card" style="max-width:520px;">
    @if ($errors->any())
        <div class="mw-alert danger">
            <ul style="margin:0;padding-left:16px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.facilities.store') }}" style="display:flex;flex-direction:column;gap:16px;">
        @csrf

        <div>
            <label class="mw-label" for="name">Name</label>
            <input class="mw-field" type="text" id="name" name="name" value="{{ old('name') }}" required>
        </div>

        <div>
            <label class="mw-label" for="facility_type">Type</label>
            <input class="mw-field" type="text" id="facility_type" name="facility_type" value="{{ old('facility_type') }}" placeholder="e.g. Rehab, Psychiatric hospital, General referral">
        </div>

        <div>
            <label class="mw-label" for="address">Address</label>
            <input class="mw-field" type="text" id="address" name="address" value="{{ old('address') }}">
        </div>

        <div>
            <label class="mw-label" for="phone">Phone</label>
            <input class="mw-field" type="text" id="phone" name="phone" value="{{ old('phone') }}">
        </div>

        <div>
            <label class="mw-label" for="notes">Notes</label>
            <textarea class="mw-field" id="notes" name="notes" rows="4">{{ old('notes') }}</textarea>
        </div>

        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-700,var(--ink-900));">
            <input type="checkbox" name="is_active" value="1" checked>
            Active (available for the routing team to assign)
        </label>

        <button type="submit" class="btn primary block" style="margin-top:6px;">Add facility</button>
    </form>
</div>
@endsection
