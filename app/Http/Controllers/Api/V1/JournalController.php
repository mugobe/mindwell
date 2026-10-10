<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
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

    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->clientProfile;

        $page = $profile
            ? JournalEntry::with('therapySession.therapistProfile.user')
                ->where('client_profile_id', $profile->id)
                ->where(fn ($q) => $q->where('kind', 'daily')->orWhereNotNull('body'))
                ->latest()
                ->paginate(20)
            : null;

        $recent = $profile
            ? JournalEntry::where('client_profile_id', $profile->id)
                ->where('kind', 'daily')->whereNotNull('mood')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->orderBy('created_at')->get(['mood', 'created_at'])
                ->groupBy(fn ($e) => $e->created_at->toDateString())
                ->map(fn ($day, $date) => ['date' => $date, 'mood' => $day->last()->mood])
                ->values()
            : collect();

        return response()->json([
            'entries' => $page ? collect($page->items())->map(fn ($e) => $this->present($e))->values() : [],
            'page' => $page?->currentPage() ?? 1,
            'has_more' => (bool) $page?->hasMorePages(),
            'last_two_weeks' => $recent,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mood' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:5000'],
            'shared_with_therapist' => ['nullable', 'boolean'],
        ], ['mood.required' => 'Pick how you feel today.']);

        $user = $request->user();
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);

        $entry = JournalEntry::create([
            'client_profile_id' => $profile->id,
            'kind' => 'daily',
            'mood' => $validated['mood'],
            'body' => $validated['body'] ?? null,
            'shared_with_therapist' => (bool) ($validated['shared_with_therapist'] ?? false),
        ]);

        return response()->json(['message' => 'Saved to your journal.', 'entry' => $this->present($entry)], 201);
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
