<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClientSubscription;
use App\Models\EmployerSeat;
use App\Models\JournalEntry;
use App\Models\TherapySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The therapist-side half of the 2026-09-25 client feedback: a therapist needs to see
 * who they've been matched with and close a session out with a structured
 * recommendation instead of free-text notes. See claude/mindwell-client-feedback.md.
 *
 * Extended 2026-09-26 per the client's follow-through request: "schedule another
 * session" now actually creates a real follow-up session (the therapist picks the
 * time right here, same credit-or-pay rules as any other session), and "refer out"
 * timestamps the case so it lands in the admin referral queue
 * (App\Http\Controllers\Admin\ReferralController) for staff to assign a facility.
 *
 * Extended again 2026-09-26 (same day, second round of testing feedback): the closure
 * report is now five structured fields instead of one free-text box, the follow-up
 * picker is a real calendar + popup instead of a plain dropdown (with its own short
 * note, stored separately from the closure report), and the therapist can review the
 * client's intake questionnaire right on this page before writing the report.
 */
class SessionController extends Controller
{
    /**
     * Duplicated from App\Http\Controllers\IntakeController on purpose -- these are
     * the same PHQ-9/GAD-7 item labels, just needed here to render a client's already-
     * submitted answers read-only. Not extracted to a shared place yet, for the same
     * reason projectOwnSlots() below isn't: small enough that the duplication is
     * cheaper than the indirection right now.
     */
    private const PHQ9_ITEMS = [
        1 => 'Little interest or pleasure in doing things',
        2 => 'Feeling down, depressed, or hopeless',
        3 => 'Trouble falling or staying asleep, or sleeping too much',
        4 => 'Feeling tired or having little energy',
        5 => 'Poor appetite or overeating',
        6 => 'Feeling bad about yourself — or that you are a failure, or have let yourself or your family down',
        7 => 'Trouble concentrating on things, such as reading or watching TV',
        8 => 'Moving or speaking so slowly that others could have noticed — or the opposite, being fidgety or restless',
        9 => 'Thoughts that you would be better off dead, or of hurting yourself in some way',
    ];

    private const GAD7_ITEMS = [
        1 => 'Feeling nervous, anxious, or on edge',
        2 => 'Not being able to stop or control worrying',
        3 => 'Worrying too much about different things',
        4 => 'Trouble relaxing',
        5 => 'Being so restless that it is hard to sit still',
        6 => 'Becoming easily annoyed or irritable',
        7 => 'Feeling afraid as if something awful might happen',
    ];

    private const ANSWER_OPTIONS = [
        0 => 'Not at all',
        1 => 'Several days',
        2 => 'More than half the days',
        3 => 'Nearly every day',
    ];

    private function ownProfile(Request $request)
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $profile->credentials_verified, 403);

        return $profile;
    }

    public function index(Request $request): View
    {
        $profile = $this->ownProfile($request);

        $upcoming = TherapySession::with('clientProfile.user')
            ->where('therapist_profile_id', $profile->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->get();

        $recentlyClosed = TherapySession::with('clientProfile.user')
            ->where('therapist_profile_id', $profile->id)
            ->where('status', 'completed')
            ->orderByDesc('closed_at')
            ->limit(10)
            ->get();

        // Their own client-rating average (2026-10-04) — null until they have at least
        // SessionRating::MIN_FOR_THERAPIST ratings. Never individual ratings or comments.
        $ratingSummary = \App\Models\SessionRating::summaryForTherapist($profile->id);

        return view('therapist.sessions.index', compact('upcoming', 'recentlyClosed', 'ratingSummary'));
    }

    public function close(Request $request, TherapySession $therapySession): View
    {
        $profile = $this->ownProfile($request);
        abort_unless($therapySession->therapist_profile_id === $profile->id, 403);
        abort_unless($therapySession->status === 'scheduled', 409, 'This session has already been closed.');

        $therapySession->load('clientProfile.user');

        $intake = $therapySession->clientProfile?->intakeAssessments()->latest()->first();

        return view('therapist.sessions.close', [
            'session' => $therapySession,
            'outcomes' => TherapySession::CLOSURE_OUTCOMES,
            'calendarDays' => $this->buildCalendarDays($this->projectOwnSlots($profile)),
            'intake' => $intake,
            'intakeResponses' => $intake ? $this->decodeIntakeResponses($intake->responses) : null,
            'phq9Items' => self::PHQ9_ITEMS,
            'gad7Items' => self::GAD7_ITEMS,
            'answerOptions' => self::ANSWER_OPTIONS,
            // Added 2026-09-29: journal entries this client has CHOSEN to share
            // (their notepad from this session first, then recent daily entries).
            // Anything they didn't share is never loaded here.
            'sharedNotes' => JournalEntry::where('client_profile_id', $therapySession->client_profile_id)
                ->where('shared_with_therapist', true)
                ->whereNotNull('body')
                ->orderByRaw('therapy_session_id = ? desc', [$therapySession->id])
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function store(Request $request, TherapySession $therapySession): RedirectResponse
    {
        $profile = $this->ownProfile($request);
        abort_unless($therapySession->therapist_profile_id === $profile->id, 403);
        abort_unless($therapySession->status === 'scheduled', 409, 'This session has already been closed.');

        $validated = $request->validate([
            'closure_outcome' => ['required', 'in:'.implode(',', array_keys(TherapySession::CLOSURE_OUTCOMES))],
            'presenting_concerns' => ['required', 'string', 'max:2000'],
            'session_summary' => ['required', 'string', 'max:2000'],
            'progress_notes' => ['required', 'string', 'max:2000'],
            'risk_assessment' => ['required', 'string', 'max:2000'],
            'plan_notes' => ['required', 'string', 'max:2000'],
            // Richer notes (2026-10-06, Dr. Ntabade's THERAPIST DRAFT) — optional.
            'interventions_used' => ['nullable', 'string', 'max:2000'],
            'homework' => ['nullable', 'string', 'max:2000'],
            'goals_progress' => ['nullable', 'string', 'max:2000'],
            'follow_up_at' => ['required_if:closure_outcome,schedule_next_session', 'nullable', 'date', 'after:now'],
            'follow_up_notes' => ['nullable', 'string', 'max:1000'],
            'follow_up_mode' => ['nullable', 'in:'.implode(',', array_keys(TherapySession::MODES))],
        ]);

        $therapySession->update([
            'status' => 'completed',
            'closure_outcome' => $validated['closure_outcome'],
            'presenting_concerns' => $validated['presenting_concerns'],
            'session_summary' => $validated['session_summary'],
            'progress_notes' => $validated['progress_notes'],
            'risk_assessment' => $validated['risk_assessment'],
            'plan_notes' => $validated['plan_notes'],
            'interventions_used' => $validated['interventions_used'] ?? null,
            'homework' => $validated['homework'] ?? null,
            'goals_progress' => $validated['goals_progress'] ?? null,
            // This client's nth completed session (with any therapist), counted now.
            'session_number' => TherapySession::where('client_profile_id', $therapySession->client_profile_id)
                ->where('status', 'completed')
                ->where('id', '!=', $therapySession->id)
                ->count() + 1,
            'closed_at' => now(),
            'referred_at' => $validated['closure_outcome'] === 'refer_to_specialist' ? now() : null,
        ]);

        AuditLog::record($request, 'session.closed', 'Therapist closed a session.', [
            'therapy_session_id' => $therapySession->id,
            'closure_outcome' => $validated['closure_outcome'],
        ]);

        if ($validated['closure_outcome'] === 'schedule_next_session' && ! empty($validated['follow_up_at'])) {
            $mode = ($validated['follow_up_mode'] ?? null) === TherapySession::MODE_IN_PERSON && $profile->offers_in_person
                ? TherapySession::MODE_IN_PERSON : TherapySession::MODE_VIDEO;
            $this->scheduleFollowUp($request, $therapySession, $profile, $validated['follow_up_at'], $validated['follow_up_notes'] ?? null, $mode);
        }

        return redirect()->route('therapist.sessions.index')->with('status', 'Session closed out.');
    }

    /**
     * Creates the actual follow-up session (2026-09-26 client decision): same client,
     * same therapist, the exact time the therapist just picked from the calendar. Goes
     * through the same credit-or-pay decision as every other session rather than
     * skipping payment just because a therapist recommended it -- that was an explicit
     * call, not an oversight. $followUpNotes (added the same day, second round of
     * feedback) is a short note about what to focus on next time -- stored on the NEW
     * session, deliberately separate from the closure report on the one being closed.
     */
    private function scheduleFollowUp(Request $request, TherapySession $original, $profile, string $followUpAt, ?string $followUpNotes, string $mode = TherapySession::MODE_VIDEO): void
    {
        $clientProfileId = $original->client_profile_id;

        // Employer seat coverage (2026-09-27) is checked FIRST, ahead of a subscription
        // credit — see the same note in IntakeController::store().
        $employerSeat = EmployerSeat::activeFor($clientProfileId);
        // usableFor() (2026-10-01) also applies renewals/expiry first, so a plan whose
        // period (and grace period) has run out can no longer be booked against.
        $activeSubscription = $employerSeat ? null : ClientSubscription::usableFor($clientProfileId);
        $isCovered = $employerSeat || $activeSubscription;

        // 2026-09-26 (third round of client testing feedback): this used to jump
        // straight to 'scheduled' the moment a credit covered it, with zero admin/
        // routing visibility. It now lands in 'pending_approval' instead -- routing has
        // to explicitly approve it (Admin\SessionRoutingController::approve()) before
        // it's confirmed to the client, same as a client's own direct booking.
        $followUp = TherapySession::create([
            'client_profile_id' => $clientProfileId,
            'therapist_profile_id' => $profile->id,
            'previous_session_id' => $original->id,
            'status' => $isCovered ? 'pending_approval' : 'pending_payment',
            'payment_method' => $employerSeat ? 'employer_seat' : ($activeSubscription ? 'credit' : null),
            'routing_method' => 'therapist_scheduled',
            'scheduled_at' => $followUpAt,
            'duration_minutes' => config('mindwell.session_duration_minutes'),
            'follow_up_notes' => $followUpNotes,
            'session_mode' => $mode,
        ]);

        if ($activeSubscription) {
            $activeSubscription->decrement('sessions_remaining');
        }

        AuditLog::record($request, 'session.followup_scheduled', 'Therapist scheduled a follow-up session while closing the previous one.', [
            'previous_session_id' => $original->id,
            'followup_session_id' => $followUp->id,
        ]);
    }

    /**
     * Same weekly-availability-to-concrete-slots projection as
     * App\Http\Controllers\TherapistBookingController::show(), just scoped to this
     * therapist's own profile instead of one a client is browsing. Kept as a separate
     * copy rather than a shared service for now -- small enough that extracting it
     * didn't seem worth the indirection yet.
     */
    private function projectOwnSlots($profile)
    {
        // 2026-10-08: the shared projection (skips booked/waiting times and time off).
        return app(\App\Services\OpenSlots::class)->for($profile, 7);
    }

    /**
     * Groups the flat 7-day slot projection above into one entry per calendar day
     * (2026-09-26, replacing the old flat <select> dropdown with a real calendar +
     * popup on the close-session page). Every one of the next 7 days gets an entry,
     * even ones with zero open slots, so the calendar strip always shows a full week
     * and can grey out days with nothing bookable.
     */
    private function buildCalendarDays(Collection $slots): Collection
    {
        $byDate = $slots->groupBy(fn (Carbon $slot) => $slot->format('Y-m-d'));
        $now = Carbon::now();
        $days = collect();

        for ($daysAhead = 0; $daysAhead < 7; $daysAhead++) {
            $date = $now->copy()->addDays($daysAhead);
            $key = $date->format('Y-m-d');

            $daySlots = ($byDate[$key] ?? collect())
                ->map(fn (Carbon $slot) => [
                    'iso' => $slot->toDateTimeString(),
                    'label' => $slot->format('g:i A'),
                ])
                ->values();

            $days->push([
                'date' => $key,
                'weekday' => $date->format('D'),
                'day' => $date->format('j'),
                'fullLabel' => $date->format('l, M j'),
                'slots' => $daySlots,
            ]);
        }

        return $days;
    }

    /**
     * Defensive decode: the IntakeAssessment model isn't owned by this controller and
     * its `responses` cast wasn't re-verified here, so this handles whichever shape it
     * comes back as (an 'array' cast, an AsCollection cast, or a raw JSON string)
     * instead of assuming one.
     */
    private function decodeIntakeResponses($responses): ?array
    {
        if ($responses instanceof Collection) {
            return $responses->toArray();
        }

        if (is_array($responses)) {
            return $responses;
        }

        if (is_string($responses) && $responses !== '') {
            $decoded = json_decode($responses, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
