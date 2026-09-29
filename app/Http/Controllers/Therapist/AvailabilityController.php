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

        $slots = $profile->availabilitySlots()
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

        $validated = $request->validate([
            'day_of_week' => ['required', 'in:'.implode(',', self::DAYS)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_recurring' => ['nullable', 'boolean'],
        ]);

        $profile->availabilitySlots()->create([
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_recurring' => $request->boolean('is_recurring', true),
        ]);

        return back()->with('status', 'Availability slot added.');
    }

    public function destroy(Request $request, AvailabilitySlot $slot): RedirectResponse
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $slot->therapist_id === $profile->id, 403);

        $slot->delete();

        return back()->with('status', 'Availability slot removed.');
    }
}