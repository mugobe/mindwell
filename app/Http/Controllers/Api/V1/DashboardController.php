<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VideoRoomController;
use App\Models\ChatConversation;
use App\Models\CheckIn;
use App\Models\ClientIntakeDetail;
use App\Models\ClientSubscription;
use App\Models\EmployerSeat;
use App\Models\JournalEntry;
use App\Models\SessionRating;
use App\Models\TherapySession;
use App\Support\ApiPresenter;
use App\Support\IntakeQuestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile API v1 — everything the app's home screen needs in one call (2026-10-09).
 * Same cards as the website dashboard: next session (with whether Join is open), a
 * session still in progress, today's mood, check-in due, "how was your session?",
 * the About-you prompt and the emergency-contact nudge.
 */
class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);

        $latestIntake = $profile->intakeAssessments()->latest()->first();

        $next = TherapySession::where('client_profile_id', $profile->id)
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now()->subMinutes((int) config('mindwell.session_duration_minutes', 70) + (int) config('mindwell.video_join_late_minutes', 30)))
            ->with('therapistProfile.user', 'venue')
            ->orderBy('scheduled_at')
            ->first();

        $latest = TherapySession::where('client_profile_id', $profile->id)->with('therapistProfile.user', 'venue')->latest()->first();
        $inProgress = $latest && in_array($latest->status, ['pending_payment', 'routing', 'pending_approval'], true) ? $latest : null;

        $join = null;
        if ($next && ! $next->isInPerson()) {
            $access = app(VideoRoomController::class)->access($next, 'client');
            $join = [
                'state' => $access['state'],
                'therapist_in_room' => $access['other_live'],
                'opens_at' => ApiPresenter::date($access['opens_at']),
                'closes_at' => ApiPresenter::date($access['closes_at']),
            ];
        }

        $moodToday = JournalEntry::where('client_profile_id', $profile->id)
            ->where('kind', 'daily')->whereNotNull('mood')
            ->where('created_at', '>=', now()->startOfDay())
            ->latest()->value('mood');

        $checkIn = CheckIn::statusFor($profile);
        $toRate = SessionRating::pendingFor($profile->id);

        $seat = EmployerSeat::activeFor($profile->id);
        $seat?->load('employerAccount');
        $sub = $seat ? null : ClientSubscription::usableFor($profile->id);

        $aboutPrompt = $latestIntake && ClientIntakeDetail::tableReady() && IntakeQuestions::anyOn() && ! ClientIntakeDetail::for($profile->id);

        return response()->json([
            'greeting_name' => $user->first_name ?: explode(' ', trim($user->name))[0],
            'intake_done' => (bool) $latestIntake,
            'next_session' => $next ? ApiPresenter::session($next) + ['join' => $join] : null,
            'in_progress' => $inProgress ? ApiPresenter::session($inProgress) : null,
            'mood_today' => $moodToday ? (int) $moodToday : null,
            'check_in' => [
                'eligible' => $checkIn['eligible'],
                'due' => $checkIn['due'],
                'available' => $checkIn['available'],
                'available_at' => ApiPresenter::date($checkIn['available_at']),
            ],
            'rate_session' => $toRate ? [
                'session_id' => $toRate->id,
                'therapist_name' => $toRate->therapistProfile?->user?->name ?? 'your therapist',
                'scheduled_at' => ApiPresenter::date($toRate->scheduled_at),
            ] : null,
            'about_you_prompt' => $aboutPrompt,
            'emergency_contact_missing' => blank($user->emergency_contact_name),
            'unread_messages' => ChatConversation::unreadTotalFor($user),
            'coverage' => $seat ? [
                'type' => 'employer',
                'label' => 'Covered by '.($seat->employerAccount?->company_name ?? 'your employer'),
            ] : ($sub ? [
                'type' => 'plan',
                'label' => ($sub->subscriptionPlan?->name ?? 'Your plan').' · '.$sub->sessions_remaining.' '.($sub->sessions_remaining === 1 ? 'session' : 'sessions').' left',
                'sessions_remaining' => (int) $sub->sessions_remaining,
            ] : null),
        ]);
    }
}
