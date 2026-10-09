<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\ClientProfile;
use App\Models\CrisisFlag;
use App\Models\JournalEntry;
use App\Models\TherapySession;
use App\Support\Screeners;
use Illuminate\Support\Collection;

/**
 * The therapist's "My clients" list and client file (2026-10-06, roadmap 71 — from
 * Dr. Ntabade's THERAPIST DRAFT). Shared by Therapist\ClientController (their own
 * clients only — decided with Collins) and Admin\ClientController (every client).
 */
class ClientRecords
{
    /** A client counts as "active" if they have an upcoming session or one this recently. */
    public const ACTIVE_DAYS = 60;

    /**
     * Rows for the client list.
     *
     * @param  string|null  $therapistProfileId  null = every client (admin)
     * @return Collection<int, array>
     */
    public function listFor(?string $therapistProfileId, ?string $search = null): Collection
    {
        $profileIds = $therapistProfileId
            ? TherapySession::where('therapist_profile_id', $therapistProfileId)->distinct()->pluck('client_profile_id')
            : null;

        $profiles = ClientProfile::with('user')
            ->when($profileIds !== null, fn ($q) => $q->whereIn('id', $profileIds->all()))
            ->get()
            ->filter(fn ($p) => $p->user && $p->user->role === 'client');

        // Richer intake (2026-10-09): one query for everyone's "About you" answers.
        \App\Models\ClientIntakeDetail::preload($profiles->pluck('id'));
        $rows = $profiles->map(fn ($profile) => $this->summary($profile, $therapistProfileId));

        if ($search !== null && trim($search) !== '') {
            $needle = mb_strtolower(trim($search));
            $digits = ltrim(preg_replace('/\D/', '', $needle), '0');

            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower($row['name']), $needle)
                || ($digits !== '' && (string) $row['client_number'] === $digits)
                || str_contains(mb_strtolower((string) $row['label']), $needle));
        }

        // Upcoming first, then most recently seen.
        return $rows->sortBy([
            fn ($a, $b) => ($a['next_at']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['next_at']?->getTimestamp() ?? PHP_INT_MAX),
            fn ($a, $b) => ($b['last_at']?->getTimestamp() ?? 0) <=> ($a['last_at']?->getTimestamp() ?? 0),
        ])->values();
    }

    /** One row: who they are, last/next session, status, latest scores. */
    public function summary(ClientProfile $profile, ?string $therapistProfileId = null): array
    {
        $user = $profile->user;
        $user?->ensureClientNumber();

        $sessions = TherapySession::where('client_profile_id', $profile->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at')
            ->get(['id', 'status', 'scheduled_at', 'therapist_profile_id', 'presenting_concerns', 'closed_at', 'created_at']);

        $now = now();
        $next = $sessions->first(fn ($s) => in_array($s->status, ['scheduled', 'pending_approval'], true) && $s->scheduled_at && $s->scheduled_at->gte($now->copy()->subHours(2)));
        $last = $sessions->filter(fn ($s) => $s->status === 'completed')->sortByDesc(fn ($s) => $s->scheduled_at ?? $s->closed_at)->first();
        $lastAt = $last?->scheduled_at ?? $last?->closed_at;

        $concern = $sessions->filter(fn ($s) => $s->status === 'completed' && filled($s->presenting_concerns))
            ->sortByDesc('closed_at')->first()?->presenting_concerns;
        // Richer intake (2026-10-09): before the first session note, show what the client
        // told us in "About you".
        $concern ??= \App\Models\ClientIntakeDetail::for($profile->id)?->concernLine();

        $timeline = CheckIn::timelineFor($profile);
        $latest = $timeline->last();

        $status = match (true) {
            CrisisFlag::where('client_id', $profile->id)->whereIn('status', ['open', 'acknowledged'])->exists() => ['danger', 'Crisis flag'],
            $last === null => ['teal', 'New'],
            $next !== null || ($lastAt && $lastAt->gte($now->copy()->subDays(self::ACTIVE_DAYS))) => ['success', 'Active'],
            default => ['', 'Inactive'],
        };

        return [
            'profile' => $profile,
            'user' => $user,
            'name' => $user?->name ?? 'Client',
            'client_number' => $user?->client_number,
            'label' => $user?->clientLabel(),
            'age' => $user?->age(),
            'gender' => $user?->gender ? (\App\Models\User::GENDERS[$user->gender] ?? $user->gender) : null,
            'concern' => $concern,
            'last_at' => $lastAt,
            'next_at' => $next?->scheduled_at,
            'next_status' => $next?->status,
            'status' => $status,
            'phq9' => $latest['phq9'] ?? null,
            'gad7' => $latest['gad7'] ?? null,
            'sessions_with_me' => $therapistProfileId ? $sessions->where('therapist_profile_id', $therapistProfileId)->count() : null,
        ];
    }

    /** Everything on the client file page. */
    public function file(ClientProfile $profile, ?string $therapistProfileId = null): array
    {
        $profile->loadMissing('user');

        $sessions = TherapySession::where('client_profile_id', $profile->id)
            ->with('therapistProfile.user')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
            ->get();

        $timeline = CheckIn::timelineFor($profile);
        $intake = $profile->intakeAssessments()->latest()->first();
        $lastCompleted = $sessions->firstWhere('status', 'completed');

        return [
            'summary' => $this->summary($profile, $therapistProfileId),
            'sessions' => $sessions,
            'timeline' => $timeline,
            'intake' => $intake,
            'latestCompleted' => $lastCompleted,
            'openFlags' => CrisisFlag::where('client_id', $profile->id)->whereIn('status', ['open', 'acknowledged'])->latest()->get(),
            'sharedNotesCount' => JournalEntry::where('client_profile_id', $profile->id)->where('shared_with_therapist', true)->whereNotNull('body')->count(),
            'checkInStatus' => CheckIn::statusFor($profile),
        ];
    }

    /** "Moderate (12)" style text for staff. */
    public static function scoreText(string $scale, ?int $score): string
    {
        return $score === null ? '—' : $score.' · '.Screeners::band($scale, $score);
    }
}
