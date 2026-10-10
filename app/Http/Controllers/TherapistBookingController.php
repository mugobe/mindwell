<?php

namespace App\Http\Controllers;

use App\Models\ClientSubscription;
use App\Models\EmployerSeat;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        // 2026-10-08: shared with reschedule + follow-ups; also skips times already
        // waiting for approval and the therapist's approved time off.
        $slots = app(\App\Services\OpenSlots::class)->for($therapist, 7);

        return view('therapists.book', [
            'therapist' => $therapist,
            'slots' => $slots,
        ]);
    }

    public function book(Request $request, TherapistProfile $therapist): RedirectResponse
    {
        abort_unless($therapist->credentials_verified, 404);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            // In-person (2026-10-07): only for therapists admin has switched it on for.
            'session_mode' => ['nullable', 'in:'.implode(',', array_keys(TherapySession::MODES))],
        ]);

        // 2026-10-08: the time must still be free (someone may have just taken it, or the
        // therapist's time off was approved meanwhile).
        $wanted = Carbon::parse($validated['scheduled_at']);

        $mode = $validated['session_mode'] ?? TherapySession::MODE_VIDEO;
        if ($mode === TherapySession::MODE_IN_PERSON && ! $therapist->offers_in_person) {
            return back()->with('error', "{$therapist->user->name} isn't seeing clients in person right now. Please book an online session.");
        }

        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        $session = $this->bookSlot($therapist, $profile, $wanted, $mode);

        if (! $session) {
            return redirect()->route('therapists.book', $therapist)->with('error', 'Sorry, that time was just taken. Please pick another.');
        }

        return $session->status === 'pending_approval'
            ? redirect()->route('sessions.pending-approval', $session)
            : redirect()->route('sessions.checkout', $session);
    }

    /**
     * Lock this therapist's row while checking + creating, so two clients pressing "Book"
     * for the same time at the same moment can't both get it. Null when the time is no
     * longer free. Shared with the mobile API (2026-10-09).
     */
    public function bookSlot(TherapistProfile $therapist, $profile, Carbon $wanted, string $mode): ?TherapySession
    {
        return DB::transaction(function () use ($therapist, $wanted, $profile, $mode) {
            TherapistProfile::whereKey($therapist->getKey())->lockForUpdate()->first();

            if (! app(\App\Services\OpenSlots::class)->for($therapist, 7)->contains(fn ($slot) => $slot->equalTo($wanted))) {
                return null;
            }

            return $this->createBooking($therapist, $profile, ['scheduled_at' => $wanted], $mode);
        });
    }

    private function createBooking(TherapistProfile $therapist, $profile, array $validated, string $mode): TherapySession
    {

        // Employer seat coverage (2026-09-27) is checked FIRST, ahead of a subscription
        // credit — see the same note in IntakeController::store().
        $employerSeat = EmployerSeat::activeFor($profile->id);
        // usableFor() (2026-10-01) also applies renewals/expiry first, so a plan whose
        // period (and grace period) has run out can no longer be booked against.
        $activeSubscription = $employerSeat ? null : ClientSubscription::usableFor($profile->id);
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
            'session_mode' => $mode,
        ]);

        if ($isCovered) {
            $activeSubscription?->decrement('sessions_remaining');
        }

        return $session;
    }
}
