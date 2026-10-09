<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\CheckIn;
use App\Models\ClientProfile;
use App\Models\SessionRating;
use App\Models\TherapySession;
use Illuminate\Support\Collection;

/**
 * Numbers for the therapist dashboard ("Today's overview") and the My stats page
 * (2026-10-06, roadmap 72 + 79 — Dr. Ntabade's THERAPIST DRAFT). Read-only.
 *
 * Definitions (kept here so the two pages always agree):
 *  - active client: an upcoming session with them, or one in the last 60 days
 *  - new client:    their first session with this therapist is upcoming or in the last 14 days
 *  - missed:        a scheduled session that finished more than the join window ago
 *                   and the client never joined the video room
 *  - to close out:  a scheduled session that has finished but isn't closed yet
 *  - follow-up:     a session the therapist scheduled when closing a previous one
 */
class TherapistOverview
{
    public function __construct(private string $therapistProfileId, private ?\App\Models\User $user = null) {}

    /** @return Collection<int, TherapySession> */
    private function sessions(): Collection
    {
        return once(fn () => TherapySession::where('therapist_profile_id', $this->therapistProfileId)
            ->with('clientProfile.user', 'venue')
            ->get());
    }

    public function today(): Collection
    {
        return $this->sessions()
            ->filter(fn ($s) => in_array($s->status, ['scheduled', 'completed'], true) && $s->scheduled_at?->isToday())
            ->sortBy('scheduled_at')
            ->values();
    }

    public function upcoming(int $days = 7): Collection
    {
        return $this->sessions()
            ->filter(fn ($s) => $s->status === 'scheduled' && $s->scheduled_at && $s->scheduled_at->gte(now()) && $s->scheduled_at->lte(now()->addDays($days)))
            ->sortBy('scheduled_at')
            ->values();
    }

    public function counts(): array
    {
        $sessions = $this->sessions();
        $now = now();
        $lateWindow = (int) config('mindwell.video_join_late_minutes', 30);

        $ended = fn ($s) => $s->status === 'scheduled' && $s->plannedEndAt() && $s->plannedEndAt()->copy()->addMinutes($lateWindow)->lt($now);

        $byClient = $sessions->where('status', '!=', 'cancelled')->groupBy('client_profile_id');

        $activeClientIds = $byClient->filter(fn ($rows) => $rows->contains(fn ($s) => ($s->status === 'scheduled' && $s->scheduled_at?->gte($now))
                || ($s->scheduled_at && $s->scheduled_at->gte($now->copy()->subDays(60)) && in_array($s->status, ['scheduled', 'completed'], true))))
            ->keys();

        $newClientIds = $byClient->filter(function ($rows) use ($now) {
            $first = $rows->filter(fn ($s) => $s->scheduled_at)->sortBy('scheduled_at')->first();

            return $first && $first->scheduled_at->gte($now->copy()->subDays(14));
        })->keys();

        $checkInsDue = ClientProfile::whereIn('id', $activeClientIds->all())->get()
            ->filter(fn ($p) => CheckIn::statusFor($p)['due'])
            ->count();

        $completed = $sessions->where('status', 'completed');

        return [
            'today' => $this->today()->where('status', 'scheduled')->count(),
            'active_clients' => $activeClientIds->count(),
            'new_clients' => $newClientIds->count(),
            'unread_messages' => ChatConversation::unreadTotalFor($this->user),
            'check_ins_due' => $checkInsDue,
            'follow_ups' => $sessions->filter(fn ($s) => $s->routing_method === 'therapist_scheduled' && in_array($s->status, ['scheduled', 'pending_approval'], true) && $s->scheduled_at?->gte($now))->count(),
            // In-person sessions (2026-10-07) never have a "joined the room" time, so once
            // they've ended they're always "to close out", never "missed".
            'to_close' => $sessions->filter(fn ($s) => $ended($s) && ($s->client_joined_at !== null || $s->isInPerson()))->count(),
            'missed' => $sessions->filter(fn ($s) => $ended($s) && $s->client_joined_at === null && ! $s->isInPerson())->count(),
            'upcoming' => $sessions->filter(fn ($s) => $s->status === 'scheduled' && $s->scheduled_at?->gte($now))->count(),
            'pending_approval' => $sessions->where('status', 'pending_approval')->count(),
            'completed_total' => $completed->count(),
            'completed_30' => $completed->filter(fn ($s) => ($s->closed_at ?? $s->scheduled_at)?->gte($now->copy()->subDays(30)))->count(),
            'referrals' => $completed->where('closure_outcome', 'refer_to_specialist')->count(),
            'rating' => SessionRating::summaryForTherapist($this->therapistProfileId),
        ];
    }

    /** Completed sessions per month, last 6 months (oldest first). */
    public function monthly(): array
    {
        $completed = $this->sessions()->where('status', 'completed');
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $out[] = [
                'label' => $month->format('M'),
                'count' => $completed->filter(fn ($s) => ($s->scheduled_at ?? $s->closed_at)?->format('Y-m') === $month->format('Y-m'))->count(),
            ];
        }

        return $out;
    }

    /** Is this the client's first session with this therapist? */
    public function isFirstWith(TherapySession $session): bool
    {
        return ! $this->sessions()->contains(fn ($s) => $s->client_profile_id === $session->client_profile_id
            && $s->id !== $session->id
            && $s->status === 'completed'
            && $s->scheduled_at && $session->scheduled_at && $s->scheduled_at->lt($session->scheduled_at));
    }
}
