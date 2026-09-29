<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My journal" — the client's own page, added 2026-09-29. It shows, newest first:
 *  - daily mood entries written here (mood 1-5 + a few words), and
 *  - their notepads from video sessions (written in the room page's side panel —
 *    see VideoRoomController::notes()), which they can re-read and edit here.
 *
 * Everything is private to the client unless they switch "Share with my therapist" on
 * for that entry (decided 2026-09-29: "Private, with optional sharing"). Shared
 * entries appear on the therapist's close-session page for this client.
 */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->clientProfile;

        $entries = $profile
            ? JournalEntry::with('therapySession.therapistProfile.user')
                ->where('client_profile_id', $profile->id)
                ->where(function ($q) {
                    // A session notepad nobody has written in yet isn't worth listing.
                    $q->where('kind', 'daily')->orWhereNotNull('body');
                })
                ->latest()
                ->paginate(20)
            : null;

        $recentMoods = $profile
            ? JournalEntry::where('client_profile_id', $profile->id)
                ->where('kind', 'daily')
                ->whereNotNull('mood')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->orderBy('created_at')
                ->get(['mood', 'created_at'])
                // One dot per day: the day's latest mood.
                ->groupBy(fn ($e) => $e->created_at->toDateString())
                ->map(fn ($day) => $day->last()->mood)
            : collect();

        return view('journal.index', [
            'entries' => $entries,
            'recentMoods' => $recentMoods,
            'moods' => JournalEntry::MOODS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mood' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:5000'],
            'shared_with_therapist' => ['nullable', 'boolean'],
        ], [
            'mood.required' => 'Pick how you feel today.',
        ]);

        $user = $request->user();
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);

        JournalEntry::create([
            'client_profile_id' => $profile->id,
            'kind' => 'daily',
            'mood' => $validated['mood'],
            'body' => $validated['body'] ?? null,
            'shared_with_therapist' => (bool) ($validated['shared_with_therapist'] ?? false),
        ]);

        return redirect()->route('journal.index')->with('status', 'Saved to your journal.');
    }

    public function update(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->authorizeOwn($request, $entry);

        $validated = $request->validate([
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'shared_with_therapist' => ['sometimes', 'boolean'],
        ]);

        $entry->update($validated);

        return redirect()->route('journal.index')->with('status', isset($validated['shared_with_therapist']) && ! isset($validated['body'])
            ? ($entry->shared_with_therapist ? 'Shared with your therapist.' : 'Now private — only you can see it.')
            : 'Entry updated.');
    }

    public function destroy(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->authorizeOwn($request, $entry);
        $entry->delete();

        return redirect()->route('journal.index')->with('status', 'Entry deleted.');
    }

    private function authorizeOwn(Request $request, JournalEntry $entry): void
    {
        abort_unless($entry->client_profile_id === $request->user()->clientProfile?->id, 403);
    }
}
