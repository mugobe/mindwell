<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AboutYouController as WebAboutYou;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IntakeController as WebIntake;
use App\Models\ClientIntakeDetail;
use App\Models\JournalEntry;
use App\Support\ApiPresenter;
use App\Support\IntakeQuestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile API v1 — intake (About you + PHQ-9/GAD-7) and the About-you page (2026-10-09).
 * The submit runs the website's own IntakeController::submit(), so crisis flags, admin
 * alerts, coverage (employer seat → plan credit → pay) and the support message are
 * identical. The app shows the crisis screen itself when outcome = 'crisis'.
 */
class IntakeController extends Controller
{
    /** The questions to show, in order. */
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);
        $items = WebIntake::screeningItems();
        $ready = ClientIntakeDetail::tableReady();

        return response()->json([
            'completed' => $profile->intakeAssessments()->exists(),
            'about' => $ready ? $this->aboutPayload($request, ClientIntakeDetail::for($profile->id)) : ['sections' => []],
            'screening' => [
                'intro' => 'Over the last 2 weeks, how often have you been bothered by…',
                'options' => collect($items['options'])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'phq9' => collect($items['phq9'])->map(fn ($text, $n) => ['key' => "phq_{$n}", 'text' => $text])->values(),
                'gad7' => collect($items['gad7'])->map(fn ($text, $n) => ['key' => "gad_{$n}", 'text' => $text])->values(),
            ],
        ]);
    }

    /**
     * Body: phq_1..phq_9, gad_1..gad_7 (0–3), plus optional about_* fields and skipped[]
     * (same names as the website form).
     */
    public function store(Request $request): JsonResponse
    {
        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        if ($profile->intakeAssessments()->exists()) {
            return response()->json(['message' => "You've already completed your intake assessment.", 'code' => 'intake_done'], 409);
        }

        $validated = $request->validate(WebIntake::screeningRules());
        $result = app(WebIntake::class)->submit($request, $validated);

        return response()->json([
            'outcome' => $result['outcome'],                 // crisis | lobby | checkout
            'message' => $result['message'],
            'session' => $result['session'] ? ApiPresenter::session($result['session']->fresh(['therapistProfile.user', 'venue'])) : null,
        ], 201);
    }

    /** "About you" on its own (existing clients / later edits). */
    public function about(Request $request): JsonResponse
    {
        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        if (! ClientIntakeDetail::tableReady() || ! IntakeQuestions::anyOn()) {
            return response()->json(['sections' => [], 'answers' => null]);
        }

        return response()->json($this->aboutPayload($request, ClientIntakeDetail::for($profile->id)));
    }

    public function updateAbout(Request $request): JsonResponse
    {
        // Same steps as the website's About you page (keeps answers for sections an admin
        // has switched off since, normalises line breaks, validates against earlier labels).
        abort_unless(ClientIntakeDetail::tableReady() && IntakeQuestions::anyOn(), 404);
        app(WebAboutYou::class)->update($request);

        $profile = $request->user()->clientProfile()->first();
        ClientIntakeDetail::forget($profile->id);

        return response()->json([
            'message' => 'Thank you — your therapist will see this before your next session.',
            'answers' => $this->answers(ClientIntakeDetail::for($profile->id)),
        ]);
    }

    public function dismissAbout(Request $request): JsonResponse
    {
        app(WebAboutYou::class)->dismiss($request);

        return response()->json(['message' => 'No problem. You can add this any time from My details.']);
    }

    private function aboutPayload(Request $request, ?ClientIntakeDetail $d): array
    {
        $sections = [];
        foreach (IntakeQuestions::enabled() as $key) {
            $sections[] = match ($key) {
                'topics' => [
                    'key' => 'topics', 'title' => 'What brings you here?', 'help' => 'Pick anything that fits — as many as you like.',
                    'fields' => [
                        ['name' => 'about_topics', 'type' => 'multi', 'options' => IntakeQuestions::topicChoices($d)],
                        ['name' => 'about_own_words', 'type' => 'text', 'label' => 'In your own words', 'max' => 2000],
                    ],
                ],
                'mood' => [
                    'key' => 'mood', 'title' => 'How are you feeling right now?', 'help' => "Just today — there's no right answer.",
                    'fields' => [['name' => 'about_mood', 'type' => 'single', 'options' => collect(JournalEntry::MOODS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()]],
                ],
                'background' => [
                    'key' => 'background', 'title' => 'A little background', 'help' => 'This helps your therapist start in the right place.',
                    'fields' => [
                        ['name' => 'about_counselling', 'type' => 'single', 'label' => 'Have you had counselling or therapy before?', 'options' => self::pairs(IntakeQuestions::COUNSELLING)],
                        ['name' => 'about_medication', 'type' => 'single', 'label' => 'Are you taking any medication for your mental health?', 'options' => self::pairs(IntakeQuestions::MEDICATION)],
                        ['name' => 'about_background', 'type' => 'text', 'label' => 'Anything else important we should know?', 'max' => 2000],
                    ],
                ],
                'goals' => [
                    'key' => 'goals', 'title' => 'What would you like to get out of therapy?', 'help' => 'Pick any that fit. You can change your mind later with your therapist.',
                    'fields' => [
                        ['name' => 'about_goals', 'type' => 'multi', 'options' => IntakeQuestions::goalChoices($d)],
                        ['name' => 'about_goals_text', 'type' => 'text', 'label' => 'In your own words', 'max' => 2000],
                    ],
                ],
                'preferences' => [
                    'key' => 'preferences', 'title' => 'Any preferences?', 'help' => "We'll share these with the team. We can't always promise them, but we'll try.",
                    'fields' => [
                        ['name' => 'about_pref_gender', 'type' => 'single', 'label' => "I'd feel most comfortable with", 'options' => self::pairs(IntakeQuestions::PREF_GENDER)],
                        ['name' => 'about_pref_mode', 'type' => 'single', 'label' => 'Sessions by', 'options' => self::pairs(IntakeQuestions::PREF_MODE)],
                        ['name' => 'about_pref_language', 'type' => 'line', 'label' => "Language you'd like to speak in", 'suggestions' => IntakeQuestions::LANGUAGE_SUGGESTIONS, 'placeholder' => $request->user()->preferred_language],
                    ],
                ],
            };
        }

        return ['sections' => $sections, 'answers' => $this->answers($d)];
    }

    private function answers(?ClientIntakeDetail $d): ?array
    {
        if (! $d) {
            return null;
        }

        return [
            'about_topics' => (array) $d->topics,
            'about_own_words' => $d->own_words,
            'about_mood' => $d->mood_now,
            'about_counselling' => $d->counselling_before,
            'about_medication' => $d->medication,
            'about_background' => $d->background_notes,
            'about_goals' => (array) $d->goals,
            'about_goals_text' => $d->goals_text,
            'about_pref_gender' => $d->pref_therapist_gender,
            'about_pref_mode' => $d->pref_mode,
            'about_pref_language' => $d->pref_language,
            'dismissed' => $d->dismissed_at !== null,
        ];
    }

    private static function pairs(array $map): array
    {
        return collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
