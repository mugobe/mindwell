<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProgressController as WebProgress;
use App\Models\CheckIn;
use App\Models\JournalEntry;
use App\Services\Notifier;
use App\Support\ApiPresenter;
use App\Support\Screeners;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile API v1 — My progress and the two-weekly check-in (2026-10-09).
 *
 * Same gentle approach as the website (pending Dr. Ntabade's decision, roadmap 56): the
 * client never gets raw PHQ-9/GAD-7 scores or clinical bands — only plain words
 * ("mild", "moderate"…), the direction since last time, and a 0–1 "level" so the app
 * can draw the same trend line the website draws.
 */
class ProgressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->clientProfile;

        $timeline = $profile ? CheckIn::timelineFor($profile) : collect();
        $count = $timeline->count();
        $latest = $count ? $timeline->last() : null;
        $previous = $count > 1 ? $timeline->get($count - 2) : null;
        $baseline = $count ? $timeline->first() : null;

        $point = fn (array $p) => [
            'at' => ApiPresenter::date($p['at']),
            'source' => $p['source'],                                   // intake | check-in
            'mood_level' => round($p['phq9'] / Screeners::PHQ9_MAX, 3),   // low mood: 0 = none … 1 = most
            'worry_level' => round($p['gad7'] / Screeners::GAD7_MAX, 3),  // worry & anxiety
            'mood_word' => Screeners::clientWord('phq9', $p['phq9']),
            'worry_word' => Screeners::clientWord('gad7', $p['gad7']),
        ];

        $status = CheckIn::statusFor($profile);

        return response()->json([
            'points' => $timeline->map($point)->values(),
            'summary' => $latest ? [
                'low_mood' => [
                    'now' => Screeners::clientWord('phq9', $latest['phq9']),
                    'since_last' => Screeners::direction($previous['phq9'] ?? null, $latest['phq9']),   // lower | higher | same | null
                    'at_start' => Screeners::clientWord('phq9', $baseline['phq9']),
                ],
                'worry' => [
                    'now' => Screeners::clientWord('gad7', $latest['gad7']),
                    'since_last' => Screeners::direction($previous['gad7'] ?? null, $latest['gad7']),
                    'at_start' => Screeners::clientWord('gad7', $baseline['gad7']),
                ],
            ] : null,
            'check_in' => [
                'eligible' => $status['eligible'],
                'available' => $status['available'],
                'due' => $status['due'],
                'available_at' => ApiPresenter::date($status['available_at']),
                'due_at' => ApiPresenter::date($status['due_at']),
            ],
            'moods' => $profile
                ? JournalEntry::dailyMoodsFor($profile->id, 60)->map(fn ($m) => ['date' => $m['at']->toDateString(), 'mood' => $m['mood']])->values()
                : [],
            'share_mood_with_therapist' => (bool) $user->share_mood_with_therapist,
        ]);
    }

    public function questions(Request $request): JsonResponse
    {
        $status = CheckIn::statusFor($request->user()->clientProfile);
        if (! $status['eligible']) {
            return response()->json(['message' => 'Start with your first assessment — check-ins come after that.', 'code' => 'intake_first'], 409);
        }
        if (! $status['available']) {
            return response()->json(['message' => 'Your next check-in opens on '.$status['available_at']->format('D j M').'.', 'code' => 'not_yet', 'available_at' => ApiPresenter::date($status['available_at'])], 409);
        }

        return response()->json([
            'intro' => 'Over the last 2 weeks, how often have you been bothered by…',
            'options' => collect(Screeners::OPTIONS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'phq9' => collect(Screeners::PHQ9_ITEMS)->map(fn ($text, $n) => ['key' => "phq_{$n}", 'text' => $text])->values(),
            'gad7' => collect(Screeners::GAD7_ITEMS)->map(fn ($text, $n) => ['key' => "gad_{$n}", 'text' => $text])->values(),
        ]);
    }

    /** Body: phq_1..phq_9, gad_1..gad_7. Returns outcome 'crisis' or 'saved'. */
    public function store(Request $request, Notifier $notifier): JsonResponse
    {
        $status = CheckIn::statusFor($request->user()->clientProfile);
        if (! $status['eligible'] || ! $status['available']) {
            return response()->json(['message' => 'A check-in isn\'t open for you right now.', 'code' => 'not_available'], 409);
        }

        $validated = $request->validate(Screeners::rules());
        $checkIn = app(WebProgress::class)->record($request, $validated, $notifier);

        return response()->json([
            'outcome' => $checkIn->self_harm ? 'crisis' : 'saved',
            'message' => $checkIn->self_harm ? null : 'Thank you — your check-in is saved.',
        ], 201);
    }

    public function shareMood(Request $request): JsonResponse
    {
        $share = (bool) $request->validate(['share' => ['required', 'boolean']])['share'];
        $request->user()->forceFill(['share_mood_with_therapist' => $share])->save();

        return response()->json([
            'share_mood_with_therapist' => $share,
            'message' => $share ? 'Your therapist can now see your mood chart (never what you write).' : 'Your mood chart is private to you again.',
        ]);
    }
}
