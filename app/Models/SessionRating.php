<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's feedback on one session (added 2026-10-04). Decided with Collins:
 *  - admins see every rating and comment, with names (Admin\RatingController);
 *  - a therapist only sees their own AVERAGE and count, and only once there are at
 *    least MIN_FOR_THERAPIST ratings, so no single client's score can be worked out —
 *    comments are never shown to therapists;
 *  - asked on the thank-you screen as the client leaves the room, then once by email
 *    the next day, plus a dashboard card. Never by SMS.
 *
 * rating is null when the client pressed "Skip" (so they're not asked again).
 */
class SessionRating extends Model
{
    use HasUuids;

    public const MIN_FOR_THERAPIST = 3;

    /** At or below this, admins get an email. */
    public const LOW = 2;

    /** How long after a session we keep asking. */
    public const ASK_FOR_DAYS = 7;

    protected $fillable = [
        'therapy_session_id',
        'client_profile_id',
        'therapist_profile_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function therapySession(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class);
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function therapistProfile(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    /**
     * The most recent session this client took part in, finished within the last week,
     * that they haven't rated or skipped yet — for the dashboard card.
     */
    public static function pendingFor(?string $clientProfileId): ?TherapySession
    {
        if (! $clientProfileId) {
            return null;
        }

        return TherapySession::where('client_profile_id', $clientProfileId)
            ->where('status', '!=', 'cancelled')
            ->where('scheduled_at', '>=', now()->subDays(self::ASK_FOR_DAYS + 1))
            ->where('scheduled_at', '<=', now())
            ->where(fn ($q) => $q->whereNotNull('client_joined_at')->orWhere('status', 'completed'))
            ->whereDoesntHave('rating')
            ->with('therapistProfile.user')
            ->orderByDesc('scheduled_at')
            ->limit(5)
            ->get()
            ->first(fn (TherapySession $s) => $s->plannedEndAt()?->lte(now()) && $s->plannedEndAt()->gte(now()->subDays(self::ASK_FOR_DAYS)));
    }

    /**
     * A therapist's own summary — null until they have MIN_FOR_THERAPIST ratings.
     *
     * @return array{average: float, count: int}|null
     */
    public static function summaryForTherapist(string $therapistProfileId): ?array
    {
        $rated = static::where('therapist_profile_id', $therapistProfileId)->whereNotNull('rating');
        $count = (clone $rated)->count();

        if ($count < self::MIN_FOR_THERAPIST) {
            return null;
        }

        return ['average' => round((float) $rated->avg('rating'), 1), 'count' => $count];
    }
}
