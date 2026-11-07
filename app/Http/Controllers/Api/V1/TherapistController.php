<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TherapistBookingController;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Services\OpenSlots;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Mobile API v1 — browse therapists, see open times, book (2026-10-09). Booking runs the
 * website's own TherapistBookingController::bookSlot() (row lock + open-slot re-check +
 * employer seat → plan credit → pay), so double-booking stays impossible.
 */
class TherapistController extends Controller
{
    public function index(): JsonResponse
    {
        $therapists = TherapistProfile::with('user')->where('credentials_verified', true)->get()
            ->sortBy(fn ($t) => $t->user?->name)->values();

        return response()->json(['therapists' => $therapists->map(fn ($t) => ApiPresenter::therapist($t))->values()]);
    }

    public function show(Request $request, TherapistProfile $therapist, OpenSlots $slots): JsonResponse
    {
        abort_unless($therapist->credentials_verified, 404);
        $therapist->loadMissing('user');

        return response()->json([
            // 2026-11-07: the first assessment comes before any booking.
            'assessment_required' => TherapistBookingController::needsAssessment($request->user()),
            'therapist' => ApiPresenter::therapist($therapist, full: true),
            'prices' => [
                'video' => (float) config('mindwell.one_off_session_price'),
                'in_person' => (float) config('mindwell.in_person_session_price'),
            ],
            'days' => $slots->for($therapist, TherapistBookingController::daysAhead())
                ->groupBy(fn ($s) => $s->toDateString())
                ->map(fn ($day, $date) => ['date' => $date, 'times' => $day->map(fn ($t) => ApiPresenter::date($t))->values()])
                ->values(),
        ]);
    }

    /** Body: scheduled_at (ISO time from the slots list), session_mode (video|in_person). */
    public function book(Request $request, TherapistProfile $therapist): JsonResponse
    {
        abort_unless($therapist->credentials_verified, 404);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'session_mode' => ['nullable', 'in:'.implode(',', array_keys(TherapySession::MODES))],
        ]);

        $mode = $validated['session_mode'] ?? TherapySession::MODE_VIDEO;
        $therapist->loadMissing('user');
        if ($mode === TherapySession::MODE_IN_PERSON && ! $therapist->offers_in_person) {
            return response()->json(['message' => ($therapist->user?->name ?? 'This therapist')." isn't seeing clients in person right now. Please book an online session."], 422);
        }

        if (TherapistBookingController::needsAssessment($request->user())) {
            return response()->json([
                'message' => 'Before your first booking, please answer a few short questions (about 5 minutes). Your therapist reads them before you meet.',
                'code' => 'assessment_required',
            ], 409);
        }

        // The slot times come from the API in the app's timezone; compare in that timezone.
        $wanted = Carbon::parse($validated['scheduled_at'])->setTimezone(config('app.timezone'));
        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        $session = app(TherapistBookingController::class)->bookSlot($therapist, $profile, $wanted, $mode);

        if (! $session) {
            return response()->json(['message' => 'Sorry, that time was just taken. Please pick another.', 'code' => 'slot_taken'], 409);
        }

        return response()->json(['session' => ApiPresenter::session($session->fresh(['therapistProfile.user', 'venue']))], 201);
    }
}
