<?php

namespace App\Http\Controllers;

use App\Models\SessionRating;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Client feedback on a session (added 2026-10-04) — see App\Models\SessionRating for
 * who sees what. Reached from the room's thank-you screen (JSON), the "how was it?"
 * email, and the dashboard card.
 */
class SessionRatingController extends Controller
{
    public function show(Request $request, TherapySession $therapySession): View|RedirectResponse
    {
        $this->authorizeClient($request, $therapySession);

        if ($therapySession->rating()->exists()) {
            return redirect()->route('dashboard')->with('status', "Thanks — you've already given feedback on that session.");
        }

        $therapySession->loadMissing('therapistProfile.user');

        return view('sessions.rate', ['session' => $therapySession]);
    }

    public function store(Request $request, TherapySession $therapySession, Notifier $notifier): JsonResponse|RedirectResponse
    {
        $this->authorizeClient($request, $therapySession);

        $validated = $request->validate([
            'skip' => ['nullable', 'boolean'],
            'rating' => ['exclude_if:skip,true', 'exclude_if:skip,1', 'required', 'integer', 'between:1,5'],
            'comment' => ['exclude_if:skip,true', 'exclude_if:skip,1', 'nullable', 'string', 'max:2000'],
        ], [
            'rating.required' => 'Tap a star to rate, or press Skip.',
        ]);

        $skipped = (bool) ($validated['skip'] ?? false);

        try {
            $rating = SessionRating::create([
                'therapy_session_id' => $therapySession->id,
                'client_profile_id' => $therapySession->client_profile_id,
                'therapist_profile_id' => $therapySession->therapist_profile_id,
                'rating' => $skipped ? null : (int) $validated['rating'],
                'comment' => $skipped ? null : (trim((string) ($validated['comment'] ?? '')) ?: null),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already rated (double click, or the email and the room both used).
            return $this->done($request, "Thanks — you've already given feedback on that session.");
        }

        if (! $skipped && $rating->rating <= SessionRating::LOW) {
            try {
                $notifier->lowRating($rating, User::where('role', 'admin')->get());
            } catch (Throwable $e) {
                Log::warning('Low-rating alert failed', ['session_rating_id' => $rating->id, 'error' => $e->getMessage()]);
            }
        }

        return $this->done($request, $skipped ? 'No problem — we won\'t ask about that session again.' : 'Thank you for your feedback.');
    }

    private function done(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message])
            : redirect()->route('dashboard')->with('status', $message);
    }

    private function authorizeClient(Request $request, TherapySession $therapySession): void
    {
        abort_unless($therapySession->client_profile_id === $request->user()->clientProfile?->id, 403);
        abort_unless($therapySession->isRateable(), 404);
    }
}
