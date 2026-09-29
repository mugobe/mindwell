<?php

namespace App\Http\Controllers;

use App\Models\ClientSubscription;
use App\Models\EmployerSeat;
use App\Models\TherapySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntakeController extends Controller
{
    /**
     * Standard PHQ-9 items. #9 (self-harm ideation) is a dedicated safety
     * item — any non-zero answer there overrides everything else below.
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

    private const OPTIONS = [
        0 => 'Not at all',
        1 => 'Several days',
        2 => 'More than half the days',
        3 => 'Nearly every day',
    ];

    public function show(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        if ($profile->intakeAssessments()->exists()) {
            return redirect()->route('dashboard')->with('status', 'You\'ve already completed your intake assessment.');
        }

        return view('intake.show', [
            'phq9Items' => self::PHQ9_ITEMS,
            'gad7Items' => self::GAD7_ITEMS,
            'options' => self::OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(self::PHQ9_ITEMS) as $i) {
            $rules["phq_{$i}"] = ['required', 'integer', 'between:0,3'];
        }
        foreach (array_keys(self::GAD7_ITEMS) as $i) {
            $rules["gad_{$i}"] = ['required', 'integer', 'between:0,3'];
        }
        $validated = $request->validate($rules);

        $phq9Score = collect(range(1, 9))->sum(fn ($i) => (int) $validated["phq_{$i}"]);
        $gad7Score = collect(range(1, 7))->sum(fn ($i) => (int) $validated["gad_{$i}"]);
        $selfHarmItem = (int) $validated['phq_9'];

        $riskLevel = match (true) {
            $selfHarmItem > 0 => 'crisis',
            $phq9Score >= 15 || $gad7Score >= 15 => 'high',
            $phq9Score >= 10 || $gad7Score >= 10 => 'moderate',
            default => 'low',
        };

        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        $intake = $profile->intakeAssessments()->create([
            'responses' => [
                'phq9' => collect(range(1, 9))->mapWithKeys(fn ($i) => [$i => (int) $validated["phq_{$i}"]]),
                'gad7' => collect(range(1, 7))->mapWithKeys(fn ($i) => [$i => (int) $validated["gad_{$i}"]]),
            ],
            'phq9_score' => $phq9Score,
            'gad7_score' => $gad7Score,
            'risk_level' => $riskLevel,
        ]);

        if ($riskLevel === 'crisis') {
            \App\Models\CrisisFlag::create([
                'client_id' => $profile->id,
                'intake_assessment_id' => $intake->id,
                'reason' => 'PHQ-9 item 9 (self-harm ideation) was answered positively on intake.',
            ]);

            return redirect()->route('intake.crisis');
        }

        // Not a crisis — move straight into the payment/routing flow the client asked for,
        // instead of just marking the intake as done. See SessionRequestController for what
        // happens next (credit check → checkout → lobby → routing).
        //
        // Employer seat coverage (2026-09-27) is checked FIRST, ahead of a subscription
        // credit: it's unmetered (nothing to decrement), so a client covered by an active
        // employer seat should never burn a subscription credit they also happen to hold.
        $employerSeat = EmployerSeat::activeFor($profile->id);
        $activeSubscription = $employerSeat ? null : ClientSubscription::where('client_profile_id', $profile->id)
            ->where('status', 'active')
            ->where('sessions_remaining', '>', 0)
            ->first();
        $isCovered = $employerSeat || $activeSubscription;

        $session = TherapySession::create([
            'client_profile_id' => $profile->id,
            'intake_assessment_id' => $intake->id,
            'status' => $isCovered ? 'routing' : 'pending_payment',
            'payment_method' => $employerSeat ? 'employer_seat' : ($activeSubscription ? 'credit' : null),
            'duration_minutes' => config('mindwell.session_duration_minutes'),
        ]);

        if ($isCovered) {
            $activeSubscription?->decrement('sessions_remaining');
            $session->update(['entered_lobby_at' => now()]);

            return redirect()->route('sessions.lobby', $session);
        }

        return redirect()->route('sessions.checkout', $session);
    }

    public function crisis(): View
    {
        return view('intake.crisis');
    }
}
