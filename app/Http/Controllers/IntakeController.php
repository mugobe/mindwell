<?php

namespace App\Http\Controllers;

use App\Models\ClientIntakeDetail;
use App\Models\ClientSubscription;
use App\Models\EmployerSeat;
use App\Models\TherapySession;
use App\Support\IntakeQuestions;
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
            // Richer intake (2026-10-09, roadmap 63): the "About you" sections an admin has on.
            'aboutSections' => ClientIntakeDetail::tableReady() ? IntakeQuestions::enabled() : [],
            // Pre-fill if they already answered from the About you page (reachable from My details).
            'aboutDetail' => ClientIntakeDetail::for($profile->id),
            'languageDefault' => $request->user()->preferred_language,
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

        // Save "About you" before anything else, so a crisis case still has it for the team.
        // It is validated SEPARATELY from the screening answers: a bad About-you answer
        // (e.g. a topic an admin renamed mid-intake) is dropped and logged, never sent back
        // as an error — that would lose the 16 screening answers and could delay someone
        // who needs the crisis screen. Wrapped so nothing here can stop the flow.
        try {
            self::saveAbout($request, $profile, self::validAbout($request, ClientIntakeDetail::for($profile->id)), 'intake');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Intake "About you" answers could not be saved', ['client_profile_id' => $profile->id, 'error' => $e->getMessage()]);
        }

        if ($riskLevel === 'crisis') {
            $flag = \App\Models\CrisisFlag::create([
                'client_id' => $profile->id,
                'intake_assessment_id' => $intake->id,
                'reason' => 'PHQ-9 item 9 (self-harm ideation) was answered positively on intake.',
            ]);

            // Phase 0 (2026-10-05): alert every admin straight away by email + SMS, the
            // same way a check-in does (no therapist yet at intake). Wrapped so the client
            // ALWAYS reaches the support screen.
            try {
                \App\Models\AuditLog::record($request, 'crisis_flag.raised', 'An intake assessment raised a crisis flag.', [
                    'crisis_flag_id' => $flag->id,
                    'intake_assessment_id' => $intake->id,
                ]);

                app(\App\Services\Notifier::class)->crisisAlert(
                    null,
                    \App\Models\User::where('role', 'admin')->get(),
                    null,
                    route('admin.clients.progress', $profile),
                    'first assessment',
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Intake crisis alert could not be sent', ['crisis_flag_id' => $flag->id, 'error' => $e->getMessage()]);
            }

            return redirect()->route('intake.crisis');
        }

        // Phase 0 (2026-10-05), from the CLIENT DRAFT: a supportive word after screening
        // — never a diagnosis. Shown as the status message on the next screen.
        $supportMessage = in_array($riskLevel, ['moderate', 'high'], true)
            ? 'Thank you for completing your assessment. Your answers suggest you may be experiencing symptoms that could benefit from professional support — you\'re in the right place.'
            : 'Thank you for completing your assessment. Your answers will help your therapist understand how you\'ve been feeling.';
        $request->session()->flash('status', $supportMessage);

        // Not a crisis — move straight into the payment/routing flow the client asked for,
        // instead of just marking the intake as done. See SessionRequestController for what
        // happens next (credit check → checkout → lobby → routing).
        //
        // Employer seat coverage (2026-09-27) is checked FIRST, ahead of a subscription
        // credit: it's unmetered (nothing to decrement), so a client covered by an active
        // employer seat should never burn a subscription credit they also happen to hold.
        $employerSeat = EmployerSeat::activeFor($profile->id);
        // usableFor() (2026-10-01) also applies renewals/expiry first, so a plan whose
        // period (and grace period) has run out can no longer be booked against.
        $activeSubscription = $employerSeat ? null : ClientSubscription::usableFor($profile->id);
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

    /**
     * Store "About you" answers (shared with AboutYouController for existing clients).
     * Mood right now also becomes a private daily mood entry; a preferred language fills
     * the client's My details language when that's still empty.
     */
    /**
     * About-you answers that pass validation. Fields that fail are dropped (and logged)
     * instead of failing the whole intake.
     */
    private static function validAbout(Request $request, ?ClientIntakeDetail $existing): array
    {
        if (! ClientIntakeDetail::tableReady() || ! IntakeQuestions::anyOn()) {
            return [];
        }
        $rules = IntakeQuestions::rules($existing);
        $keys = array_values(array_unique(array_map(fn ($k) => explode('.', $k)[0], array_keys($rules))));
        $data = $request->only($keys);

        $v = \Illuminate\Support\Facades\Validator::make($data, $rules);
        if ($v->fails()) {
            $bad = array_values(array_unique(array_map(fn ($k) => explode('.', $k)[0], array_keys($v->failed()))));
            \Illuminate\Support\Facades\Log::warning('Intake "About you" answers partly dropped', ['fields' => $bad]);
            $data = array_diff_key($data, array_flip($bad));
            $v = \Illuminate\Support\Facades\Validator::make($data, $rules);
            if ($v->fails()) {
                return [];
            }
        }

        return $v->validated();
    }

    public static function saveAbout(Request $request, $profile, array $validated, string $source): ?ClientIntakeDetail
    {
        if (! ClientIntakeDetail::tableReady() || ! IntakeQuestions::anyOn()) {
            return null;
        }

        $existing = ClientIntakeDetail::for($profile->id);
        $attrs = IntakeQuestions::attributesFrom($validated);
        // Keep earlier answers for sections an admin has switched off since.
        if ($existing) {
            foreach (IntakeQuestions::FIELDS as $section => $fields) {
                if (! IntakeQuestions::isOn($section)) {
                    foreach ($fields as $f) {
                        $attrs[$f] = $existing->{$f};
                    }
                }
            }
        }
        $previousMood = $existing?->mood_now;
        $detail = ClientIntakeDetail::updateOrCreate(
            ['client_profile_id' => $profile->id],
            $attrs + ['source' => $source, 'completed_at' => now(), 'dismissed_at' => null],
        );
        ClientIntakeDetail::forget($profile->id);

        // Only log a mood entry when it's new information (re-saving the About you page
        // with the same pre-filled mood shouldn't add another entry).
        if ($attrs['mood_now'] && $attrs['mood_now'] !== $previousMood) {
            \App\Models\JournalEntry::create([
                'client_profile_id' => $profile->id,
                'kind' => 'daily',
                'mood' => $attrs['mood_now'],
                'shared_with_therapist' => false,
            ]);
        }

        $user = $request->user();
        if ($attrs['pref_language'] && blank($user->preferred_language)) {
            $user->forceFill(['preferred_language' => mb_substr($attrs['pref_language'], 0, 60)])->save();
        }

        return $detail;
    }

    public function crisis(): View
    {
        return view('intake.crisis');
    }
}
