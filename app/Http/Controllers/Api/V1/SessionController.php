<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VideoRoomController;
use App\Models\JournalEntry;
use App\Models\ScheduleChangeRequest;
use App\Models\TherapySession;
use App\Services\OpenSlots;
use App\Services\ScheduleChanges;
use App\Services\SessionRouter;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Mobile API v1 — the client's sessions (2026-10-09): list, one session, the waiting
 * lobby, giving up waiting, asking to move/cancel, and whether the video room is open.
 * Video tokens, presence, the in-room notepad and ratings use the website's own
 * VideoRoomController / SessionRatingController (they already speak JSON).
 */
class SessionController extends Controller
{
    private const CHANGE_DAYS_AHEAD = 14;

    private function own(Request $request, TherapySession $s): void
    {
        abort_unless($s->client_profile_id === $request->user()->clientProfile?->id, 403);
    }

    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->clientProfile;

        $sessions = $profile
            ? TherapySession::where('client_profile_id', $profile->id)
                ->with('therapistProfile.user', 'venue')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()
            : collect();

        $now = now();
        $upcoming = $sessions->filter(fn ($s) => in_array($s->status, ['pending_payment', 'routing', 'pending_approval'], true)
            || ($s->status === 'scheduled' && ($s->plannedEndAt()?->copy()->addMinutes((int) config('mindwell.video_join_late_minutes', 30))->gte($now) ?? true)))
            ->sortBy(fn ($s) => $s->scheduled_at?->getTimestamp() ?? PHP_INT_MAX)->values();
        $past = $sessions->reject(fn ($s) => $upcoming->contains('id', $s->id))->values();

        return response()->json([
            'upcoming' => $upcoming->map(fn ($s) => ApiPresenter::session($s))->values(),
            'past' => $past->map(fn ($s) => ApiPresenter::session($s))->values(),
        ]);
    }

    public function show(Request $request, TherapySession $therapySession): JsonResponse
    {
        $this->own($request, $therapySession);
        $therapySession->load('therapistProfile.user', 'venue');

        $join = null;
        if ($therapySession->status === 'scheduled' && ! $therapySession->isInPerson()) {
            $access = app(VideoRoomController::class)->access($therapySession, 'client');
            $join = [
                'state' => $access['state'],                 // too_early | open | ended | closed
                'therapist_in_room' => $access['other_live'],
                'opens_at' => ApiPresenter::date($access['opens_at']),
                'closes_at' => ApiPresenter::date($access['closes_at']),
            ];
        }

        $pending = ScheduleChangeRequest::where('therapy_session_id', $therapySession->id)->where('status', 'pending')->latest()->first();

        return response()->json([
            'session' => ApiPresenter::session($therapySession) + [
                'join' => $join,
                'change_request' => $pending ? [
                    'id' => $pending->id,
                    'type' => $pending->type,
                    'proposed_at' => ApiPresenter::date($pending->proposed_at),
                    'mine' => $pending->requester_role === 'client',
                    'summary' => $pending->summary(),
                ] : null,
            ],
        ]);
    }

    /**
     * Polled by the app's lobby every few seconds (like the website's lobby). Each call
     * makes one routing attempt while the session is still waiting.
     */
    public function lobby(Request $request, TherapySession $therapySession): JsonResponse
    {
        $this->own($request, $therapySession);

        if ($therapySession->status === 'routing') {
            app(SessionRouter::class)->attempt($therapySession);
            $therapySession->refresh();
        }

        $waitingSince = $therapySession->entered_lobby_at ?? $therapySession->updated_at;

        return response()->json([
            'session' => ApiPresenter::session($therapySession->load('therapistProfile.user', 'venue')),
            'waiting_since' => ApiPresenter::date($waitingSince),
            'target_minutes' => (int) config('mindwell.lobby_target_minutes', 10),
        ]);
    }

    /** "Stop waiting — I'll pick a therapist myself" (same rule as the website). */
    public function stopWaiting(Request $request, TherapySession $therapySession): JsonResponse
    {
        $this->own($request, $therapySession);

        if (in_array($therapySession->status, ['routing', 'pending_payment'], true)) {
            $therapySession->update(['status' => 'cancelled']);
        }

        return response()->json(['session' => ApiPresenter::session($therapySession->fresh(['therapistProfile.user', 'venue']))]);
    }

    /** Open times the client can move to (therapist's free slots, next 14 days). */
    public function changeOptions(Request $request, TherapySession $therapySession, OpenSlots $slots): JsonResponse
    {
        $this->own($request, $therapySession);

        if ($therapySession->status !== 'scheduled' || ! $therapySession->scheduled_at?->isFuture()) {
            return response()->json(['message' => 'Only upcoming confirmed sessions can be changed.'], 409);
        }

        $free = $therapySession->therapistProfile
            ? $slots->for($therapySession->therapistProfile, self::CHANGE_DAYS_AHEAD, $therapySession->id)
            : collect();

        return response()->json([
            'notice_hours' => (int) config('mindwell.cancellation.notice_hours', 24),
            'days' => $free->groupBy(fn ($s) => $s->toDateString())
                ->map(fn ($day, $date) => ['date' => $date, 'times' => $day->map(fn ($t) => ApiPresenter::date($t))->values()])
                ->values(),
        ]);
    }

    public function requestChange(Request $request, TherapySession $therapySession, ScheduleChanges $changes, OpenSlots $slots): JsonResponse
    {
        $this->own($request, $therapySession);

        $data = $request->validate([
            'type' => ['required', 'in:reschedule,cancel'],
            'proposed_at' => ['required_if:type,reschedule', 'nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], ['proposed_at.required_if' => 'Pick a new time.']);

        // Same as booking: store the clock time in the app's timezone (Kampala), whatever
        // offset the phone sent.
        $newAt = filled($data['proposed_at'] ?? null) ? Carbon::parse($data['proposed_at'])->setTimezone(config('app.timezone')) : null;

        if ($data['type'] === 'reschedule' && $therapySession->therapistProfile
            && ! $slots->for($therapySession->therapistProfile, self::CHANGE_DAYS_AHEAD, $therapySession->id)->contains(fn ($s) => $s->equalTo($newAt))) {
            return response()->json(['message' => 'Sorry, that time is no longer free. Please pick another.'], 409);
        }

        $req = $changes->request($request, $therapySession, $request->user(), 'client', $data['type'], $newAt, $data['reason'] ?? null);

        return response()->json([
            'message' => 'Request sent. Our team will confirm it and let you know — your session stays as it is until then.',
            'change_request_id' => $req->id,
        ], 201);
    }

    public function withdrawChange(Request $request, ScheduleChangeRequest $changeRequest, ScheduleChanges $changes): JsonResponse
    {
        abort_unless($changeRequest->requester_role === 'client'
            && $changeRequest->therapySession?->client_profile_id === $request->user()->clientProfile?->id, 403);

        $changes->withdraw($request, $changeRequest);

        return response()->json(['message' => 'Request withdrawn. Your session stays as it is.']);
    }

    /** Room state without minting a token (the app polls this before showing Join). */
    public function room(Request $request, TherapySession $therapySession): JsonResponse
    {
        $this->own($request, $therapySession);

        if ($therapySession->isInPerson()) {
            return response()->json(['message' => 'That is an in-person session, so there is no video room for it.', 'state' => 'in_person'], 409);
        }

        $access = app(VideoRoomController::class)->access($therapySession, 'client');

        return response()->json([
            'state' => $access['state'],
            'therapist_in_room' => $access['other_live'],
            'opens_at' => ApiPresenter::date($access['opens_at']),
            'closes_at' => ApiPresenter::date($access['closes_at']),
            'ends_at' => ApiPresenter::date($therapySession->plannedEndAt()),
            'therapist_name' => $therapySession->therapistProfile?->user?->name,
            // The client's own in-room notepad (their private journal entry for this
            // session), so the app can show what they already wrote — added for the
            // Flutter app's room screen, 2026-10-09.
            'notes' => (function () use ($therapySession) {
                $note = JournalEntry::where('client_profile_id', $therapySession->client_profile_id)
                    ->where('therapy_session_id', $therapySession->id)
                    ->first();

                return ['body' => $note?->body ?? '', 'shared' => (bool) $note?->shared_with_therapist];
            })(),
        ]);
    }
}
