<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\AvailabilitySlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function index(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->therapistProfile;

        if (! $profile || ! $profile->credentials_verified) {
            return redirect()->route('dashboard');
        }

        // Weekly blocks, plus one-off blocks from today on (2026-11-07). Past one-offs drop off.
        $slots = $profile->availabilitySlots()
            ->where(fn ($q) => $q->where('is_recurring', true)->orWhereNull('on_date')->orWhere('on_date', '>=', now()->toDateString()))
            ->orderBy('on_date')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('therapist.availability.index', [
            'days' => self::DAYS,
            'slots' => $slots,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $profile->credentials_verified, 403);

        // 2026-11-07: "Repeats weekly" can really be unticked now (the form always sends
        // 0 or 1). Not weekly = one date, and the day of the week comes from that date.
        $weekly = $request->has('is_recurring') ? $request->boolean('is_recurring') : true;

        $validated = $request->validate([
            'day_of_week' => [$weekly ? 'required' : 'nullable', 'in:'.implode(',', self::DAYS)],
            'on_date' => [$weekly ? 'nullable' : 'required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_recurring' => ['nullable', 'boolean'],
        ], [
            'on_date.required' => 'Pick the date for these one-off hours.',
            'on_date.after_or_equal' => 'Pick today or a later date.',
        ]);

        $date = $weekly ? null : \Illuminate\Support\Carbon::parse($validated['on_date']);

        $profile->availabilitySlots()->create([
            'day_of_week' => $weekly ? $validated['day_of_week'] : strtolower($date->format('l')),
            'on_date' => $date?->toDateString(),
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_recurring' => $weekly,
        ]);

        return back()->with('status', $weekly
            ? 'Weekly hours added.'
            : 'Hours added for '.$date->format('l j F').' only.');
    }

    public function destroy(Request $request, AvailabilitySlot $slot): RedirectResponse
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $slot->therapist_id === $profile->id, 403);

        $slot->delete();

        return back()->with('status', 'Availability slot removed.');
    }
}