<?php

namespace App\Http\Controllers;

use App\Models\ScheduleChangeRequest;
use App\Models\TherapySession;
use App\Services\OpenSlots;
use App\Services\ScheduleChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Client: "Change or cancel" a confirmed session (2026-10-08, roadmap 75). The client
 * asks — picking a new time from the therapist's open slots — and admin approves.
 */
class SessionChangeController extends Controller
{
    private const DAYS_AHEAD = 14;

    private function ownSession(Request $request, TherapySession $therapySession): void
    {
        abort_unless($therapySession->client_profile_id === $request->user()->clientProfile?->id, 403);
    }

    public function show(Request $request, TherapySession $therapySession, OpenSlots $slots): View|RedirectResponse
    {
        $this->ownSession($request, $therapySession);

        if ($therapySession->status !== 'scheduled' || ! $therapySession->scheduled_at?->isFuture()) {
            return redirect()->route('sessions.history')->with('error', 'Only upcoming confirmed sessions can be changed.');
        }

        $therapySession->load('therapistProfile.user', 'venue');

        return view('sessions.change', [
            'session' => $therapySession,
            'pending' => ScheduleChangeRequest::where('therapy_session_id', $therapySession->id)->where('status', 'pending')->latest()->first(),
            'slots' => $therapySession->therapistProfile
                ? $slots->for($therapySession->therapistProfile, self::DAYS_AHEAD, $therapySession->id)->groupBy(fn ($s) => $s->toDateString())
                : collect(),
            'noticeHours' => (int) config('mindwell.cancellation.notice_hours', 24),
        ]);
    }

    public function store(Request $request, TherapySession $therapySession, ScheduleChanges $changes, OpenSlots $slots): RedirectResponse
    {
        $this->ownSession($request, $therapySession);

        $data = $request->validate([
            'type' => ['required', 'in:reschedule,cancel'],
            'proposed_at' => ['required_if:type,reschedule', 'nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], ['proposed_at.required_if' => 'Pick a new time.']);

        $newAt = filled($data['proposed_at'] ?? null) ? Carbon::parse($data['proposed_at']) : null;

        // A client can only pick one of the therapist's open times.
        if ($data['type'] === 'reschedule' && $therapySession->therapistProfile
            && ! $slots->for($therapySession->therapistProfile, self::DAYS_AHEAD, $therapySession->id)->contains(fn ($s) => $s->equalTo($newAt))) {
            return back()->with('error', 'Sorry, that time is no longer free. Please pick another.');
        }

        $changes->request($request, $therapySession, $request->user(), 'client', $data['type'], $newAt, $data['reason'] ?? null);

        return redirect()->route('sessions.change', $therapySession)
            ->with('status', 'Request sent. Our team will confirm it and let you know — your session stays as it is until then.');
    }

    public function withdraw(Request $request, ScheduleChangeRequest $changeRequest, ScheduleChanges $changes): RedirectResponse
    {
        abort_unless($changeRequest->requester_role === 'client'
            && $changeRequest->therapySession?->client_profile_id === $request->user()->clientProfile?->id, 403);

        $changes->withdraw($request, $changeRequest);

        return redirect()->route('sessions.change', $changeRequest->therapy_session_id)->with('status', 'Request withdrawn. Your session stays as it is.');
    }
}
