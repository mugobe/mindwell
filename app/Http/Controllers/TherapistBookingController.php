<?php

namespace App\Http\Controllers;

use App\Models\ClientSubscription;
use App\Models\EmployerSeat;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The "browse" half of the agreed flow — used two ways:
 *  1. Continuity: a returning client rebooking with a therapist they've already seen.
 *  2. Fallback: the release valve if the lobby's routing genuinely can't place someone
 *     (or if a client just prefers to pick someone themselves instead of being routed).
 * Deliberately NOT the original "shop through profiles as the front door" design —
 * see claude/mindwell-betterhelp-proposal.md for why that changed.
 */
class TherapistBookingController extends Controller
{
    public function browse(): View
    {
        $therapists = TherapistProfile::with('user')->where('credentials_verified', true)->get();

        return view('therapists.browse', compact('therapists'));
    }

    /**
     * Projects the therapist's weekly recurring availability forward across the next 7
     * days into concrete, bookable date+time slots, skipping ones already taken.
     * There's no support for a therapist's one-off (non-recurring) slots here yet —
     * only `is_recurring` weekly patterns, since that's the only kind in real use so far.
     */
    public function show(TherapistProfile $therapist): View
    {
        abort_unless($therapist->credentials_verified, 404);

        $now = Carbon::now();
        $slots = collect();

        for ($daysAhead = 0; $daysAhead < 7; $daysAhead++) {
            $date = $now->copy()->addDays($daysAhead);
            $dayName = strtolower($date->format('l'));

            foreach ($therapist->availabilitySlots()->where('day_of_week', $dayName)->where('is_recurring', true)->get() as $slot) {
                $candidate = $date->copy()->setTimeFromTimeString((string) $slot->start_time);

                if ($candidate->isPast()) {
                    continue;
                }

                $taken = TherapySession::where('therapist_profile_id', $therapist->id)
                    ->where('status', 'scheduled')
                    ->where('scheduled_at', $candidate)
                    ->exists();

                if (! $taken) {
                    $slots->push($candidate);
                }
            }
        }

        return view('therapists.book', [
            'therapist' => $therapist,
            'slots' => $slots->sort()->values(),
        ]);
    }

    public function book(Request $request, TherapistProfile $therapist): RedirectResponse
    {
        abort_unless($therapist->credentials_verified, 404);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        // Employer seat coverage (2026-09-27) is checked FIRST, ahead of a subscription
        // credit — see the same note in IntakeController::store().
        $employerSeat = EmployerSeat::activeFor($profile->id);
        $activeSubscription = $employerSeat ? null : ClientSubscription::where('client_profile_id', $profile->id)
            ->where('status', 'active')
            ->where('sessions_remaining', '>', 0)
            ->first();
        $isCovered = $employerSeat || $activeSubscription;

        // 2026-09-26 (third round of client testing feedback): a direct booking used to
        // go straight to 'scheduled' the moment a credit covered it, with zero admin/
        // routing visibility. It now lands in 'pending_approval' instead -- routing has
        // to explicitly approve it (Admin\SessionRoutingController::approve()) before
        // it's confirmed to the client. The no-credit path still goes through checkout
        // first; SessionRequestController::pay() applies the same pending_approval step
        // there once "payment" completes.
        $session = TherapySession::create([
            'client_profile_id' => $profile->id,
            'therapist_profile_id' => $therapist->id,
            'status' => $isCovered ? 'pending_approval' : 'pending_payment',
            'payment_method' => $employerSeat ? 'employer_seat' : ($activeSubscription ? 'credit' : null),
            'routing_method' => 'self_booked',
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => config('mindwell.session_duration_minutes'),
        ]);

        if ($isCovered) {
            $activeSubscription?->decrement('sessions_remaining');

            return redirect()->route('sessions.pending-approval', $session);
        }

        return redirect()->route('sessions.checkout', $session);
    }
}
