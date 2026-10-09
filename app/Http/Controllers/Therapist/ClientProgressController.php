<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\ClientProfile;
use App\Models\CrisisFlag;
use App\Models\JournalEntry;
use App\Models\TherapySession;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A therapist's view of one client's progress (added 2026-10-03): full PHQ-9 / GAD-7
 * scores with the standard bands since intake, open crisis flags, and — only if the
 * client has switched on sharing — their daily mood chart (numbers only).
 *
 * Only for therapists who have (or had) a session with this client.
 */
class ClientProgressController extends Controller
{
    public function show(Request $request, ClientProfile $clientProfile): View
    {
        $therapist = $request->user()->therapistProfile;
        abort_unless($therapist && $therapist->credentials_verified, 403);
        abort_unless(
            TherapySession::where('client_profile_id', $clientProfile->id)
                ->where('therapist_profile_id', $therapist->id)
                ->exists(),
            403
        );

        $clientProfile->loadMissing('user');
        $timeline = CheckIn::timelineFor($clientProfile);
        $sharesMood = (bool) $clientProfile->user?->share_mood_with_therapist;

        return view('therapist.progress.show', [
            'client' => $clientProfile,
            'timeline' => $timeline,
            'openFlags' => CrisisFlag::where('client_id', $clientProfile->id)
                ->whereIn('status', ['open', 'acknowledged'])
                ->latest()
                ->get(),
            'sharesMood' => $sharesMood,
            'moods' => $sharesMood ? JournalEntry::dailyMoodsFor($clientProfile->id, 60) : collect(),
            'moodLabels' => JournalEntry::MOODS,
            'status' => CheckIn::statusFor($clientProfile),
            'viewer' => 'therapist',
            'backUrl' => route('therapist.sessions.index'),
            'backLabel' => 'Back to sessions',
        ]);
    }
}
