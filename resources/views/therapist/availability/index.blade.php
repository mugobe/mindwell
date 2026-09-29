@extends('layouts.app')
@section('title', 'Availability')
@section('content')

<div class="dash-head">
    <h1>Your availability</h1>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="section-title">Add a time block</div>
    <form method="POST" action="{{ route('therapist.availability.store') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        @csrf
        <div>
            <label class="mw-label" for="day_of_week">Day</label>
            <select class="mw-field" id="day_of_week" name="day_of_week" required>
                @foreach ($days as $day)
                    <option value="{{ $day }}" {{ old('day_of_week') === $day ? 'selected' : '' }}>{{ ucfirst($day) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mw-label" for="start_time">From</label>
            <input class="mw-field" type="time" id="start_time" name="start_time" value="{{ old('start_time') }}" required>
        </div>
        <div>
            <label class="mw-label" for="end_time">To</label>
            <input class="mw-field" type="time" id="end_time" name="end_time" value="{{ old('end_time') }}" required>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-700);padding-bottom:11px;">
            <input type="checkbox" name="is_recurring" value="1" checked>
            Repeats weekly
        </label>
        <button type="submit" class="btn primary sm" style="height:41px;">Add slot</button>
    </form>

    @error('day_of_week')<div class="mw-error">{{ $message }}</div>@enderror
    @error('start_time')<div class="mw-error">{{ $message }}</div>@enderror
    @error('end_time')<div class="mw-error">{{ $message }}</div>@enderror
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Day</th>
                <th>Time blocks</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($days as $day)
            <tr>
                <td style="padding-left:16px;font-weight:700;vertical-align:top;">{{ ucfirst($day) }}</td>
                <td colspan="2" style="padding-top:12px;padding-bottom:12px;">
                    @forelse ($slots->get($day, collect()) as $slot)
                        <div style="display:inline-flex;align-items:center;gap:8px;margin:0 8px 8px 0;">
                            <span class="pill success">
                                {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }}
                                –
                                {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}
                                @unless ($slot->is_recurring) · one-off @endunless
                            </span>
                            <form method="POST" action="{{ route('therapist.availability.destroy', $slot) }}" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:var(--ink-300);cursor:pointer;font-size:14px;line-height:1;" title="Remove">&times;</button>
                            </form>
                        </div>
                    @empty
                        <span style="color:var(--ink-300);font-size:12.5px;">Closed</span>
                    @endforelse
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection