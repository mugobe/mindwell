<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\JournalController as WebJournal;
use App\Models\JournalEntry;
use App\Services\JournalDays;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile API v1 — My journal: daily mood + notes, and session notepads (2026-10-09).
 * Private unless the client shares an entry with their therapist (same as the website).
 */
class JournalController extends Controller
{
    private function present(JournalEntry $e): array
    {
        return [
            'id' => $e->id,
            'kind' => $e->kind,                     // daily | session
            'mood' => $e->mood,
            'mood_label' => $e->moodLabel(),
            'body' => $e->body,
            'shared_with_therapist' => (bool) $e->shared_with_therapist,
            'session' => $e->therapySession ? [
                'id' => $e->therapySession->id,
                'scheduled_at' => ApiPresenter::date($e->therapySession->scheduled_at),
                'therapist_name' => $e->therapySession->therapistProfile?->user?->name,
            ] : null,
            'created_at' => ApiPresenter::date($e->created_at),
            'updated_at' => ApiPresenter::date($e->updated_at),
        ];
    }

    public function index(Request $request, JournalDays $journal): JsonResponse
    {
        $profile = $request->user()->clientProfile;

        // 2026-11-07: ?grouped=1 gives the journal as days (one mood per day, then that
        // day's written entries). Without it, the old flat list for older app versions.
        if ($request->boolean('grouped')) {
            $days = $profile ? $journal->days($profile, max(1, (int) $request->query('page', 1))) : null;
            $today = $profile ? $journal->todayMoods($profile) : collect();

            return response()->json([
                'today' => [
                    'mood' => $today->last()?->mood,
                    'mood_label' => $today->last()?->moodLabel(),
                ],
                'days' => $days ? collect($days->items())->map(fn ($d) => [
                    'date' => $d['date']->toDateString(),
                    'mood' => $d['mood'],
                    'mood_label' => $d['mood'] ? (JournalEntry::MOODS[$d['mood']] ?? null) : null,
                    'changes' => collect($d['changes'])->map(fn ($c) => ['mood' => $c['mood'], 'at' => ApiPresenter::date($c['at'])])->values(),
                    'entries' => $d['entries']->map(fn ($e) => $this->present($e))->values(),
                ])->values() : [],
                'page' => $days?->currentPage() ?? 1,
                'has_more' => (bool) $days?->hasMorePages(),
                'last_two_weeks' => $this->lastTwoWeeks($profile),
            ]);
        }

        $page = $profile
            ? JournalEntry::with('therapySession.therapistProfile.user')
                ->where('client_profile_id', $profile->id)
                ->where(fn ($q) => $q->where('kind', 'daily')->orWhereNotNull('body'))
                ->latest()
                ->paginate(20)
            : null;

        return response()->json([
            'entries' => $page ? collect($page->items())->map(fn ($e) => $this->present($e))->values() : [],
            'page' => $page?->currentPage() ?? 1,
            'has_more' => (bool) $page?->hasMorePages(),
            'last_two_weeks' => $this->lastTwoWeeks($profile),
        ]);
    }

    private function lastTwoWeeks($profile)
    {
        return $profile
            ? JournalEntry::where('client_profile_id', $profile->id)
                ->where('kind', 'daily')->whereNotNull('mood')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->orderBy('created_at')->get(['mood', 'created_at'])
                ->groupBy(fn ($e) => $e->created_at->toDateString())
                ->map(fn ($day, $date) => ['date' => $date, 'mood' => $day->last()->mood])
                ->values()
            : collect();
    }

    /**
     * Body: mood (1–5) and/or body, shared_with_therapist. 2026-11-07: one mood per day
     * (a second face the same day changes it), writing unlimited. `action` says which:
     * mood_saved | mood_updated | mood_same | written.
     */
    public function store(Request $request, JournalDays $journal): JsonResponse
    {
        $validated = $request->validate(WebJournal::rules(), WebJournal::messages());

        $user = $request->user();
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);

        $result = $journal->record(
            $profile,
            isset($validated['mood']) ? (int) $validated['mood'] : null,
            $validated['body'] ?? null,
            (bool) ($validated['shared_with_therapist'] ?? false),
        );

        return response()->json([
            'message' => $result['message'],
            'action' => $result['action'],
            'entry' => $result['entry'] ? $this->present($result['entry']) : null,
        ], 201);
    }

    public function update(Request $request, JournalEntry $entry): JsonResponse
    {
        abort_unless($entry->client_profile_id === $request->user()->clientProfile?->id, 403);

        $validated = $request->validate([
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'shared_with_therapist' => ['sometimes', 'boolean'],
        ]);

        $entry->update($validated);

        return response()->json(['entry' => $this->present($entry->fresh('therapySession.therapistProfile.user'))]);
    }

    public function destroy(Request $request, JournalEntry $entry): JsonResponse
    {
        abort_unless($entry->client_profile_id === $request->user()->clientProfile?->id, 403);
        $entry->delete();

        return response()->json(['message' => 'Entry deleted.']);
    }
}
