<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EmployerSeat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Client-facing half of employer seat redemption (2026-09-27) -- the admin side
 * (purchasing seats, generating codes) already existed in Admin\EmployerAccountController.
 * This is the "gentler" opt-in page the user chose over surfacing it during onboarding,
 * same placement pattern as SubscriptionController's "My plan" page.
 *
 * Unlike a subscription, redeeming a seat doesn't grant a credit count -- it just marks
 * the seat as this client's for as long as it stays active and the employer's contract
 * hasn't lapsed (see EmployerSeat::activeFor()). Every session while that holds is free,
 * with nothing to decrement. See IntakeController::store(), TherapistBookingController::
 * book(), and Therapist\SessionController::scheduleFollowUp() for where that's checked.
 */
class EmployerSeatController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->clientProfile;

        $seat = $profile ? EmployerSeat::activeFor($profile->id) : null;

        if ($seat) {
            $seat->load('employerAccount');
        }

        return view('employer-seat.index', [
            'seat' => $seat,
        ]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seat_code' => ['required', 'string', 'max:32'],
        ]);

        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        $result = $this->redeemCode($request, $profile, $validated['seat_code']);

        if (isset($result['error'])) {
            return back()->withErrors(['seat_code' => $result['error']])->withInput();
        }

        return redirect()->route('employer-seat.index')->with('status', $result['message']);
    }

    /**
     * The checks + redemption, shared with the mobile API (2026-10-09).
     * Returns ['error' => string] or ['seat' => EmployerSeat, 'message' => string].
     */
    public function redeemCode(Request $request, $profile, string $rawCode): array
    {
        if (EmployerSeat::activeFor($profile->id)) {
            return ['error' => 'You already have an active employer benefit -- a client can only be covered by one employer seat at a time.'];
        }

        // Codes are generated uppercase (Admin\EmployerAccountController::store()); normalize
        // whatever the client typed so stray casing/whitespace doesn't cause a false miss.
        $code = strtoupper(trim($rawCode));

        $seat = EmployerSeat::where('seat_code', $code)->with('employerAccount')->first();

        if (! $seat) {
            return ['error' => "That code doesn't match any employer benefit. Double-check it and try again."];
        }

        if ($seat->client_profile_id) {
            return ['error' => 'That code has already been redeemed by someone else.'];
        }

        if (! $seat->is_active) {
            return ['error' => 'That code is no longer active.'];
        }

        if (! $seat->employerAccount || ! $seat->employerAccount->isCurrentlyActive()) {
            return ['error' => "This employer's benefit isn't currently active -- their contract may have ended."];
        }

        $seat->update([
            'client_profile_id' => $profile->id,
            'assigned_at' => now(),
        ]);

        AuditLog::record($request, 'employer_seat.redeemed', "{$request->user()->name} redeemed an employer seat from \"{$seat->employerAccount->company_name}\".", [
            'employer_seat_id' => $seat->id,
            'employer_account_id' => $seat->employer_account_id,
        ]);

        return ['seat' => $seat, 'message' => "You're covered by {$seat->employerAccount->company_name} -- sessions are free while this stays active."];
    }
}
