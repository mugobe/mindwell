<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Services\JournalDays;
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
 *
 * 2026-11-07: one mood per day, writing unlimited, list grouped by day (App\Services\JournalDays).
 */
class JournalController extends Controller
{
    public function index(Request $request, JournalDays $journal): View
    {
        $profile = $request->user()->clientProfile;

        $days = $profile ? $journal->days($profile, max(1, (int) $request->query('page', 1))) : null;
        $today = $profile ? $journal->todayMoods($profile) : collect();

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
            'days' => $days,
            'todayMood' => $today->last()?->mood,
            'todayChanges' => $today->pluck('mood')->unique()->count() > 1,
            'recentMoods' => $recentMoods,
            'moods' => JournalEntry::MOODS,
        ]);
    }

    public function store(Request $request, JournalDays $journal): RedirectResponse
    {
        $validated = $request->validate(self::rules(), self::messages());

        $user = $request->user();
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);

        $result = $journal->record(
            $profile,
            isset($validated['mood']) ? (int) $validated['mood'] : null,
            $validated['body'] ?? null,
            (bool) ($validated['shared_with_therapist'] ?? false),
        );

        // Phase 0 (2026-10-05): the dashboard's "How are you feeling today?" posts here too.
        if ($request->input('return') === 'dashboard') {
            return redirect()->route('dashboard')->with('status', $result['action'] === 'mood_saved'
                ? 'Thank you for checking in. It is saved in your journal.'
                : $result['message']);
        }

        return redirect()->route('journal.index')->with('status', $result['message']);
    }

    /** Shared with the mobile API: a face, or some words, or both. */
    public static function rules(): array
    {
        return [
            'mood' => ['nullable', 'required_without:body', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:5000'],
            'shared_with_therapist' => ['nullable', 'boolean'],
        ];
    }

    public static function messages(): array
    {
        return ['mood.required_without' => 'Pick how you feel today, or write a few words.'];
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
            ? ($entry->shared_with_therapist ? 'Shared with your therapist.' : 'This is now private. Only you can see it.')
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
