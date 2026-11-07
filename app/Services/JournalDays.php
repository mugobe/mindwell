<?php

namespace App\Services;

use App\Models\ClientProfile;
use App\Models\JournalEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The journal as days (2026-11-07, Collins: "one is at liberty to enter as many as possible
 * per day ... does that make sense"). Agreed rules, like Apple's State of Mind and Daylio:
 *
 *  - ONE MOOD PER DAY. Tapping a face again the same day changes today's mood instead of
 *    adding another entry. A change within 10 minutes simply corrects the last tap; a
 *    later change is kept (so a day that swung from Low to Great is still visible), but
 *    the day counts once everywhere (charts, "Most days", progress, the therapist's view
 *    already use the day's latest mood: JournalEntry::dailyMoodsFor()).
 *  - WRITING IS NEVER LIMITED. A written entry can be added at any time. The face is
 *    optional when writing; it defaults to today's mood.
 *  - THE LIST IS GROUPED BY DAY: the day's mood (and how it changed) on top, then that
 *    day's written entries and session notes. A face with no words is not its own card.
 *
 * Nothing is deleted or merged in the database except the "correct the last tap" case.
 */
class JournalDays
{
    /** Taps within this many minutes of the last mood-only check-in correct it. */
    public const CORRECTION_MINUTES = 10;

    /**
     * Save a check-in and/or a written entry.
     *
     * @return array{action: 'mood_saved'|'mood_updated'|'mood_same'|'written', entry: ?JournalEntry, message: string}
     */
    public function record(ClientProfile $profile, ?int $mood, ?string $body, bool $shared = false): array
    {
        $body = filled($body) ? trim($body) : null;
        $today = $this->todayMoods($profile);
        $current = $today->last();

        if ($body !== null) {
            $entry = JournalEntry::create([
                'client_profile_id' => $profile->id,
                'kind' => 'daily',
                'mood' => $mood ?? $current?->mood,
                'body' => $body,
                'shared_with_therapist' => $shared,
            ]);

            return ['action' => 'written', 'entry' => $entry, 'message' => 'Saved to your journal.'];
        }

        if ($current && $current->mood === $mood) {
            return ['action' => 'mood_same', 'entry' => $current, 'message' => "That's already today's mood."];
        }

        // A quick change of mind: correct the last tap instead of keeping both.
        if ($current && blank($current->body) && $current->created_at->gt(now()->subMinutes(self::CORRECTION_MINUTES))) {
            $current->update(['mood' => $mood]);

            return ['action' => 'mood_updated', 'entry' => $current, 'message' => "Today's mood is updated."];
        }

        $entry = JournalEntry::create([
            'client_profile_id' => $profile->id,
            'kind' => 'daily',
            'mood' => $mood,
            'body' => null,
            'shared_with_therapist' => false,
        ]);

        return $current
            ? ['action' => 'mood_updated', 'entry' => $entry, 'message' => "Today's mood is updated."]
            : ['action' => 'mood_saved', 'entry' => $entry, 'message' => 'Thank you for checking in today.'];
    }

    /** Today's mood check-ins (oldest first). The last one is today's mood. */
    public function todayMoods(ClientProfile $profile): Collection
    {
        return JournalEntry::where('client_profile_id', $profile->id)
            ->where('kind', 'daily')->whereNotNull('mood')
            ->where('created_at', '>=', now()->startOfDay())
            ->orderBy('created_at')->orderBy('id')
            ->get();
    }

    public function todayMood(?ClientProfile $profile): ?int
    {
        return $profile ? $this->todayMoods($profile)->last()?->mood : null;
    }

    /**
     * Days with anything in them, newest first, $perPage days a page. Each day:
     *   date     Carbon (start of day)
     *   mood     the day's mood (its latest), or null
     *   changes  [['mood' => int, 'at' => Carbon], ...] when the mood changed during the day
     *   entries  written entries and session notes, newest first
     *   moodOnly the mood-only check-ins of that day
     */
    public function days(ClientProfile $profile, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        $base = fn () => JournalEntry::where('client_profile_id', $profile->id)
            ->where(fn ($q) => $q->where('kind', 'daily')->orWhereNotNull('body'));

        // groupBy (not distinct) so the page count counts days, not rows.
        $dates = $base()->selectRaw('DATE(created_at) as d')->groupByRaw('DATE(created_at)')->orderByDesc('d')
            ->paginate($perPage, ['*'], 'page', $page);

        $list = collect($dates->items())->map(fn ($r) => (string) $r->d)->filter()->values();
        if ($list->isEmpty()) {
            return new LengthAwarePaginator([], $dates->total(), $perPage, $page, ['path' => request()->url()]);
        }

        $from = Carbon::parse($list->last())->startOfDay();
        $to = Carbon::parse($list->first())->endOfDay();
        $rows = $base()->with('therapySession.therapistProfile.user')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')->orderBy('id')
            ->get()
            ->groupBy(fn ($e) => $e->created_at->toDateString());

        $days = $list->map(function ($date) use ($rows) {
            $all = $rows->get($date, collect());
            $moods = $all->where('kind', 'daily')->whereNotNull('mood')->values();

            $changes = [];
            foreach ($moods as $m) {
                if (! $changes || end($changes)['mood'] !== $m->mood) {
                    $changes[] = ['mood' => $m->mood, 'at' => $m->created_at];
                }
            }

            return [
                'date' => Carbon::parse($date),
                'mood' => $moods->last()?->mood,
                'changes' => count($changes) > 1 ? $changes : [],
                'entries' => $all->filter(fn ($e) => filled($e->body))->sortByDesc('created_at')->values(),
                'moodOnly' => $all->filter(fn ($e) => $e->kind === 'daily' && blank($e->body))->values(),
            ];
        });

        return new LengthAwarePaginator($days, $dates->total(), $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }
}
