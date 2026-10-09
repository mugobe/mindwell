<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\ScheduleChangeRequest;
use App\Models\TherapySession;
use App\Models\TimeOff;
use App\Services\CalendarBuilder;
use App\Services\ScheduleChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Therapist → Calendar (2026-10-08, roadmap 75 — Dr. Ntabade's THERAPIST DRAFT item 7):
 * day / week / month of their own sessions and time off; ask to move or cancel a session,
 * ask for time off. Admin decides every request (Collins).
 */
class CalendarController extends Controller
{
    private function ownProfile(Request $request)
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $profile->credentials_verified, 403);

        return $profile;
    }

    public function index(Request $request, CalendarBuilder $builder): View
    {
        $profile = $this->ownProfile($request);
        $cal = $builder->build((string) $request->query('view', 'week'), $request->query('date'), [$profile->id], false);
        $focus = $request->query('focus');

        return view('therapist.calendar', [
            'cal' => $cal,
            'focus' => $focus,
            'ev' => $focus ? $cal['events']->firstWhere('id', $focus) : null,
            'timeOff' => TimeOff::where('therapist_profile_id', $profile->id)
                ->whereIn('status', [TimeOff::PENDING, TimeOff::APPROVED])
                ->where('ends_at', '>', now())->orderBy('starts_at')->get(),
            'requests' => ScheduleChangeRequest::with('therapySession.clientProfile.user')
                ->whereHas('therapySession', fn ($q) => $q->where('therapist_profile_id', $profile->id))
                ->where('status', ScheduleChangeRequest::PENDING)->latest()->get(),
        ]);
    }

    public function requestChange(Request $request, TherapySession $therapySession, ScheduleChanges $changes): RedirectResponse
    {
        $profile = $this->ownProfile($request);
        abort_unless($therapySession->therapist_profile_id === $profile->id, 403);

        $data = $request->validate([
            'type' => ['required', 'in:reschedule,cancel'],
            'proposed_at' => ['required_if:type,reschedule', 'nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:type,cancel'],
        ]);

        $changes->request($request, $therapySession, $request->user(), 'therapist', $data['type'],
            filled($data['proposed_at'] ?? null) ? Carbon::parse($data['proposed_at']) : null, $data['reason'] ?? null);

        return back()->with('status', 'Request sent. The team will approve it and let the client know.');
    }

    public function withdraw(Request $request, ScheduleChangeRequest $changeRequest, ScheduleChanges $changes): RedirectResponse
    {
        $profile = $this->ownProfile($request);
        abort_unless($changeRequest->requester_role === 'therapist'
            && $changeRequest->therapySession?->therapist_profile_id === $profile->id, 403);

        $changes->withdraw($request, $changeRequest);

        return back()->with('status', 'Request withdrawn.');
    }

    public function storeTimeOff(Request $request, ScheduleChanges $changes): RedirectResponse
    {
        $profile = $this->ownProfile($request);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $changes->requestTimeOff($request, $profile, Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']), $data['reason'] ?? null);

        return back()->with('status', 'Time off requested. Clients can still book those times until the team approves it.');
    }

    public function cancelTimeOff(Request $request, TimeOff $timeOff): RedirectResponse
    {
        $profile = $this->ownProfile($request);
        abort_unless($timeOff->therapist_profile_id === $profile->id, 403);
        abort_unless(in_array($timeOff->status, [TimeOff::PENDING, TimeOff::APPROVED], true), 409);

        $timeOff->update(['status' => TimeOff::CANCELLED]);
        \App\Models\AuditLog::record($request, 'time_off.cancelled', 'Removed time off: '.$timeOff->rangeLabel().'.', ['time_off_id' => $timeOff->id]);

        return redirect()->route('therapist.calendar')->with('status', 'Time off removed.');
    }
}
