<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\CrisisFlag;
use App\Models\JournalEntry;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Screeners;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * The client's "My progress" page and the two-weekly check-in (added 2026-10-03).
 * See App\Models\CheckIn for the decisions behind it.
 */
class ProgressController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->clientProfile;

        $timeline = $profile ? CheckIn::timelineFor($profile) : collect();
        $count = $timeline->count();

        return view('progress.index', [
            'timeline' => $timeline,
            'latest' => $count ? $timeline->last() : null,
            'previous' => $count > 1 ? $timeline->get($count - 2) : null,
            'baseline' => $count ? $timeline->first() : null,
            'status' => CheckIn::statusFor($profile),
            'moods' => $profile ? JournalEntry::dailyMoodsFor($profile->id, 60) : collect(),
            'moodLabels' => JournalEntry::MOODS,
            'shareMood' => (bool) $user->share_mood_with_therapist,
        ]);
    }

    public function checkIn(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->refuse($request)) {
            return $redirect;
        }

        return view('progress.check-in', [
            'phq9Items' => Screeners::PHQ9_ITEMS,
            'gad7Items' => Screeners::GAD7_ITEMS,
            'options' => Screeners::OPTIONS,
        ]);
    }

    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        if ($redirect = $this->refuse($request)) {
            return $redirect;
        }

        $validated = $request->validate(Screeners::rules());
        $result = Screeners::score($validated);
        $profile = $request->user()->clientProfile;

        $checkIn = CheckIn::create([
            'client_profile_id' => $profile->id,
            'phq9_score' => $result['phq9'],
            'gad7_score' => $result['gad7'],
            'responses' => $result['responses'],
            'self_harm' => $result['self_harm'],
        ]);

        if ($result['self_harm']) {
            $flag = CrisisFlag::create([
                'client_id' => $profile->id,
                'intake_assessment_id' => null,
                'reason' => 'PHQ-9 item 9 (thoughts of self-harm) answered positively on a progress check-in.',
            ]);
            $checkIn->update(['crisis_flag_id' => $flag->id]);

            AuditLog::record($request, 'crisis_flag.raised', 'A progress check-in raised a crisis flag.', [
                'crisis_flag_id' => $flag->id,
                'check_in_id' => $checkIn->id,
            ]);

            // Alert their therapist + every admin. Wrapped so the client ALWAYS gets the
            // support screen, whatever happens with email/SMS.
            try {
                $therapist = TherapySession::where('client_profile_id', $profile->id)
                    ->whereNotNull('therapist_profile_id')
                    ->with('therapistProfile.user')
                    ->latest()
                    ->first()
                    ?->therapistProfile?->user;

                $notifier->crisisAlert(
                    $therapist,
                    User::where('role', 'admin')->get(),
                    route('therapist.clients.progress', $profile),
                    route('admin.clients.progress', $profile),
                );
            } catch (Throwable $e) {
                Log::error('Crisis alert could not be sent', ['crisis_flag_id' => $flag->id, 'error' => $e->getMessage()]);
            }

            return redirect()->route('progress.crisis');
        }

        return redirect()->route('progress.index')->with('status', 'Thank you — your check-in is saved.');
    }

    public function crisis(): View
    {
        return view('progress.crisis');
    }

    /** "Let my therapist see my mood chart" switch. */
    public function shareMood(Request $request): RedirectResponse
    {
        $share = (bool) $request->validate(['share' => ['required', 'boolean']])['share'];

        $request->user()->forceFill(['share_mood_with_therapist' => $share])->save();

        return redirect()->route('progress.index')->with('status', $share
            ? 'Your therapist can now see your mood chart (never what you write).'
            : 'Your mood chart is private to you again.');
    }

    /** Null if a check-in is allowed right now, otherwise where to send them instead. */
    private function refuse(Request $request): ?RedirectResponse
    {
        $status = CheckIn::statusFor($request->user()->clientProfile);

        if (! $status['eligible']) {
            return redirect()->route('intake.show')
                ->with('status', 'Start with your first assessment — check-ins come after that.');
        }

        if (! $status['available']) {
            return redirect()->route('progress.index')
                ->with('status', 'Your next check-in opens on '.$status['available_at']->format('D j M').'.');
        }

        return null;
    }
}
