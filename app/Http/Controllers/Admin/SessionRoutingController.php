<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClientSubscription;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Models\Venue;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The manual-routing fallback the client explicitly asked for: "falling back to a staff
 * member manually routing them to an available specialist if the algorithm doesn't
 * place them." Anything sitting in 'routing' status showed up here because
 * App\Services\SessionRouter couldn't find anyone free right now.
 *
 * Extended 2026-09-26 (third round of client testing feedback): a directly-booked
 * session or a therapist-scheduled follow-up used to skip this page entirely and jump
 * straight to 'scheduled'. Both now land in 'pending_approval' first, and this
 * controller is where routing explicitly approves or declines them -- see approve()
 * and decline() below.
 */
class SessionRoutingController extends Controller
{
    public function index(): View
    {
        $waiting = TherapySession::with('clientProfile.user', 'intakeAssessment')
            ->where('status', 'routing')
            ->orderBy('entered_lobby_at')
            ->get();

        $verifiedTherapists = TherapistProfile::with('user')->where('credentials_verified', true)->get();

        // New 2026-09-26: every direct booking and therapist-scheduled follow-up sits
        // here, unconfirmed, until routing approves or declines it.
        $pendingApprovals = TherapySession::with('clientProfile.user', 'therapistProfile.user')
            ->where('status', 'pending_approval')
            ->orderBy('created_at')
            ->get();

        // Visibility only, no action needed here: once approved above, a follow-up
        // shows up in this list purely so the team has a record of it happening.
        $recentFollowUps = TherapySession::with('clientProfile.user', 'therapistProfile.user')
            ->where('routing_method', 'therapist_scheduled')
            ->where('status', 'scheduled')
            ->latest('approved_at')
            ->limit(10)
            ->get();

        // In-person sessions (2026-10-07): venues to pick from on approval, and the
        // upcoming confirmed ones so admin can change where they happen.
        $venues = Venue::where('is_active', true)->orderBy('name')->get();
        $upcomingInPerson = TherapySession::with('clientProfile.user', 'therapistProfile.user', 'venue')
            ->where('session_mode', TherapySession::MODE_IN_PERSON)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now()->subHours(2))
            ->orderBy('scheduled_at')
            ->get();

        return view('admin.session-routing.index', compact('waiting', 'verifiedTherapists', 'pendingApprovals', 'recentFollowUps', 'venues', 'upcomingInPerson'));
    }

    public function assign(Request $request, TherapySession $therapySession): RedirectResponse
    {
        $validated = $request->validate([
            'therapist_profile_id' => ['required', 'exists:therapist_profiles,id'],
        ]);

        abort_unless($therapySession->status === 'routing', 409, 'This session is no longer waiting to be routed.');

        $therapySession->update([
            'therapist_profile_id' => $validated['therapist_profile_id'],
            'status' => 'scheduled',
            'routing_method' => 'manual',
            'scheduled_at' => now(),
            'routed_at' => now(),
        ]);

        AuditLog::record($request, 'session.manually_routed', 'Manually routed a waiting client to a therapist.', ['therapy_session_id' => $therapySession->id]);

        return back()->with('status', 'Client routed. They should move off the lobby screen within a few seconds.');
    }

    /**
     * Confirms a direct booking or therapist-scheduled follow-up. Only after this does
     * the client see it as "Scheduled" — see SessionRequestController::routeForStatus().
     */
    public function approve(Request $request, TherapySession $therapySession): RedirectResponse
    {
        abort_unless($therapySession->status === 'pending_approval', 409, 'This session is no longer awaiting approval.');

        // In-person (2026-10-07): admin says where it happens before confirming, so the
        // confirmation email the client and therapist get already has the address.
        $location = $therapySession->isInPerson() ? $this->validatedLocation($request) : [];

        $therapySession->update(array_merge($location, [
            'status' => 'scheduled',
            'approved_at' => now(),
        ]));

        AuditLog::record($request, 'session.approved', 'Approved a directly booked or therapist-scheduled session.', [
            'therapy_session_id' => $therapySession->id,
            'routing_method' => $therapySession->routing_method,
        ]);

        return back()->with('status', 'Session approved and confirmed for the client.');
    }

    /**
     * Change where a confirmed in-person session happens (2026-10-07). The client and
     * therapist are told about the new place.
     */
    public function location(Request $request, TherapySession $therapySession): RedirectResponse
    {
        abort_unless($therapySession->isInPerson() && $therapySession->status === 'scheduled', 409, 'Only confirmed in-person sessions have a location to change.');

        $therapySession->update($this->validatedLocation($request));

        AuditLog::record($request, 'session.location_changed', 'Changed where an in-person session happens: '.$therapySession->fresh('venue')->locationText(), [
            'therapy_session_id' => $therapySession->id,
        ]);

        app(Notifier::class)->sessionLocationChanged($therapySession);

        return back()->with('status', 'Location updated. The client and therapist have been told.');
    }

    /** A venue from the list, extra details (room, floor, or a one-off place), or both. */
    private function validatedLocation(Request $request): array
    {
        $data = $request->validate([
            'venue_id' => ['nullable', 'uuid', 'exists:venues,id'],
            'location_details' => ['nullable', 'string', 'max:1000', 'required_without:venue_id'],
        ], [
            'location_details.required_without' => 'Pick a venue or type where the session will happen.',
        ]);

        return [
            'venue_id' => $data['venue_id'] ?? null,
            'location_details' => filled($data['location_details'] ?? null) ? trim($data['location_details']) : null,
        ];
    }

    /**
     * Rejects a direct booking or therapist-scheduled follow-up. Refunds the session
     * credit if one was used to book it -- there's no per-session link to which
     * subscription paid for it, so this best-effort refunds to whichever active
     * subscription the client currently has, same as how the credit was found in the
     * first place. No real charge exists yet for the one-off payment path (see the
     * PLACEHOLDER note on SessionRequestController::pay()), so nothing to refund there.
     * An 'employer_seat' session (2026-09-27) also needs no refund here -- coverage
     * isn't metered, so declining one doesn't cost the client anything to get back.
     */
    public function decline(Request $request, TherapySession $therapySession): RedirectResponse
    {
        abort_unless($therapySession->status === 'pending_approval', 409, 'This session is no longer awaiting approval.');

        if ($therapySession->payment_method === 'credit') {
            ClientSubscription::where('client_profile_id', $therapySession->client_profile_id)
                ->where('status', 'active')
                ->first()
                ?->increment('sessions_remaining');
        }

        $therapySession->update(['status' => 'cancelled']);

        AuditLog::record($request, 'session.declined', 'Declined a directly booked or therapist-scheduled session.', [
            'therapy_session_id' => $therapySession->id,
            'routing_method' => $therapySession->routing_method,
        ]);

        return back()->with('status', 'Session declined. The client will need to book again.');
    }
}
