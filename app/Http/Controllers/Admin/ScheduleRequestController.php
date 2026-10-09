<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScheduleChangeRequest;
use App\Models\TimeOff;
use App\Services\ScheduleChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin → Schedule requests (2026-10-08, roadmap 75): requests to move or cancel a
 * session (from clients and therapists) and therapists' time off — admin decides each.
 */
class ScheduleRequestController extends Controller
{
    public function index(ScheduleChanges $changes): View
    {
        $pending = ScheduleChangeRequest::with('therapySession.clientProfile.user', 'therapySession.therapistProfile.user', 'requester')
            ->where('status', ScheduleChangeRequest::PENDING)->oldest()->get()
            ->map(function ($r) use ($changes) {
                $s = $r->therapySession;
                $r->setAttribute('mw_money', $r->type === 'cancel' && $s?->status === 'scheduled' ? $changes->moneyDefaults($s, $r->created_at) : null);
                $r->setAttribute('mw_clash', $r->type === 'reschedule' && $s && $r->proposed_at ? $changes->clashes($s, $r->proposed_at) : false);

                return $r;
            });

        $timeOff = TimeOff::with('therapistProfile.user')->where('status', TimeOff::PENDING)->orderBy('starts_at')->get();

        $recent = ScheduleChangeRequest::with('therapySession.clientProfile.user', 'therapySession.therapistProfile.user', 'decider')
            ->whereIn('status', [ScheduleChangeRequest::APPROVED, ScheduleChangeRequest::DECLINED, ScheduleChangeRequest::WITHDRAWN])
            ->latest('decided_at')->limit(20)->get();

        return view('admin.schedule-requests.index', compact('pending', 'timeOff', 'recent'));
    }

    public function approve(Request $request, ScheduleChangeRequest $changeRequest, ScheduleChanges $changes): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'proposed_at' => ['nullable', 'date'],
        ]);

        $changes->approve($request, $changeRequest, $request->user(), [
            'return_credit' => $request->boolean('return_credit'),
            'refund' => $request->boolean('refund'),
            'note' => $data['note'] ?? null,
            'proposed_at' => filled($data['proposed_at'] ?? null) ? Carbon::parse($data['proposed_at']) : null,
        ]);

        return back()->with('status', 'Approved. The client and therapist have been told.');
    }

    public function decline(Request $request, ScheduleChangeRequest $changeRequest, ScheduleChanges $changes): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $changes->decline($request, $changeRequest, $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Declined. The person who asked has been told; the session stays as it is.');
    }

    public function decideTimeOff(Request $request, TimeOff $timeOff, ScheduleChanges $changes): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500'], 'approve' => ['required', 'boolean']]);
        $changes->decideTimeOff($request, $timeOff, $request->user(), (bool) $data['approve'], $data['note'] ?? null);

        return back()->with('status', $data['approve'] ? 'Time off approved — clients can no longer book those times.' : 'Time off declined.');
    }
}
