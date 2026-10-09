<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScheduleChangeRequest;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Models\TimeOff;
use App\Services\CalendarBuilder;
use App\Services\ScheduleChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin → Calendar (2026-10-08, roadmap 75): every therapist's sessions and time off,
 * filterable by therapist; admin can move or cancel a session straight away.
 */
class CalendarController extends Controller
{
    public function index(Request $request, CalendarBuilder $builder, ScheduleChanges $changes): View
    {
        $therapists = TherapistProfile::with('user')->where('credentials_verified', true)->get()->sortBy(fn ($t) => $t->user?->name)->values();
        $filter = (string) $request->query('therapist', '');
        $ids = $filter !== '' && $therapists->contains('id', $filter) ? [$filter] : null;

        $cal = $builder->build((string) $request->query('view', 'week'), $request->query('date'), $ids, $ids === null);
        $focus = $request->query('focus');
        $ev = $focus ? $cal['events']->firstWhere('id', $focus) : null;

        return view('admin.calendar', [
            'cal' => $cal,
            'focus' => $focus,
            'ev' => $ev,
            'therapists' => $therapists,
            'filter' => $ids ? $filter : '',
            'money' => ($ev['kind'] ?? null) === 'session' && $ev['session']->status === 'scheduled' ? $changes->moneyDefaults($ev['session']) : null,
            'clashes' => ($ev['kind'] ?? null) === 'timeoff' ? $ev['timeOff']->clashingSessions() : null,
            'pendingCount' => ScheduleChangeRequest::where('status', 'pending')->count() + TimeOff::where('status', TimeOff::PENDING)->count(),
        ]);
    }

    public function reschedule(Request $request, TherapySession $therapySession, ScheduleChanges $changes): RedirectResponse
    {
        $data = $request->validate([
            'proposed_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $changes->direct($request, $therapySession, $request->user(), ScheduleChangeRequest::TYPE_RESCHEDULE,
            Carbon::parse($data['proposed_at']), $data['reason'] ?? null, ['allow_clash' => $request->boolean('allow_clash')]);

        return back()->with('status', 'Session moved. The client and therapist have been told.');
    }

    public function cancel(Request $request, TherapySession $therapySession, ScheduleChanges $changes): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $changes->direct($request, $therapySession, $request->user(), ScheduleChangeRequest::TYPE_CANCEL, null, $data['reason'] ?? null, [
            'return_credit' => $request->boolean('return_credit'),
            'refund' => $request->boolean('refund'),
        ]);

        return back()->with('status', 'Session cancelled. The client and therapist have been told.');
    }
}
