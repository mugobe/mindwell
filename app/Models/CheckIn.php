<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A progress check-in (added 2026-10-03): the client re-answers PHQ-9 + GAD-7 every
 * two weeks so they and their therapist can see what's changing since intake.
 *
 * Decided with Collins via AskUserQuestion:
 *  - same PHQ-9 + GAD-7 as intake (scores comparable with the baseline);
 *  - every 2 weeks (config mindwell.check_in_interval_days), dashboard card first,
 *    email/SMS nudge once a couple of days overdue;
 *  - the client sees a GENTLE trend (App\Support\Screeners::clientWord), the therapist
 *    sees full scores and standard bands;
 *  - a positive self-harm answer → crisis screen + CrisisFlag + an immediate discreet
 *    email/SMS to their therapist and every admin (App\Services\Notifier::crisisAlert).
 */
class CheckIn extends Model
{
    use HasUuids;

    /** Session statuses that mean "this person is actually in care right now". */
    private const ACTIVE_STATUSES = ['routing', 'pending_approval', 'scheduled', 'completed'];

    protected $fillable = [
        'client_profile_id',
        'phq9_score',
        'gad7_score',
        'responses',
        'self_harm',
        'crisis_flag_id',
    ];

    protected function casts(): array
    {
        return [
            'phq9_score' => 'integer',
            'gad7_score' => 'integer',
            'responses' => 'array',
            'self_harm' => 'boolean',
        ];
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function crisisFlag(): BelongsTo
    {
        return $this->belongsTo(CrisisFlag::class);
    }

    /**
     * Every scored point for a client, oldest first: their intake assessment(s) as the
     * baseline, then each check-in.
     *
     * @return Collection<int, array{at: Carbon, phq9: int, gad7: int, source: string, self_harm: bool}>
     */
    public static function timelineFor(ClientProfile $profile): Collection
    {
        $intakes = $profile->intakeAssessments()
            ->get()
            ->map(fn ($intake) => [
                'at' => Carbon::instance($intake->created_at),
                'phq9' => (int) $intake->phq9_score,
                'gad7' => (int) $intake->gad7_score,
                'source' => 'intake',
                'self_harm' => $intake->risk_level === 'crisis',
            ]);

        $checkIns = static::where('client_profile_id', $profile->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (self $checkIn) => [
                'at' => Carbon::instance($checkIn->created_at),
                'phq9' => $checkIn->phq9_score,
                'gad7' => $checkIn->gad7_score,
                'source' => 'check-in',
                'self_harm' => $checkIn->self_harm,
            ]);

        return $intakes->concat($checkIns)
            ->sortBy(fn (array $point) => $point['at']->getTimestamp())
            ->values();
    }

    /**
     * Where a client stands with check-ins.
     *
     * @return array{eligible: bool, available: bool, due: bool, last_at: ?Carbon, due_at: ?Carbon, available_at: ?Carbon, overdue_days: int}
     */
    public static function statusFor(?ClientProfile $profile): array
    {
        $none = ['eligible' => false, 'available' => false, 'due' => false, 'last_at' => null, 'due_at' => null, 'available_at' => null, 'overdue_days' => 0];

        if (! $profile) {
            return $none;
        }

        $lastIntake = $profile->intakeAssessments()->latest()->value('created_at');
        if (! $lastIntake) {
            return $none; // no baseline yet — intake comes first
        }

        $lastCheckIn = static::where('client_profile_id', $profile->id)->latest()->value('created_at');

        $last = Carbon::parse($lastIntake);
        if ($lastCheckIn && Carbon::parse($lastCheckIn)->gt($last)) {
            $last = Carbon::parse($lastCheckIn);
        }

        $dueAt = $last->copy()->addDays((int) config('mindwell.check_in_interval_days', 14));
        $availableAt = $last->copy()->addDays((int) config('mindwell.check_in_min_gap_days', 7));
        $due = now()->gte($dueAt) && static::isInCare($profile->id);

        return [
            'eligible' => true,
            'available' => now()->gte($availableAt),
            'due' => $due,
            'last_at' => $last,
            'due_at' => $dueAt,
            'available_at' => $availableAt,
            'overdue_days' => $due ? (int) floor($dueAt->diffInDays(now(), true)) : 0,
        ];
    }

    /**
     * Only people who are actually seeing someone get nudged: a live session, or one in
     * the last 90 days. Someone who did intake and never came back isn't pestered.
     */
    public static function isInCare(string $clientProfileId): bool
    {
        $since = now()->subDays(90);

        return TherapySession::where('client_profile_id', $clientProfileId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where(fn ($q) => $q->where('scheduled_at', '>=', $since)->orWhere('created_at', '>=', $since))
            ->exists();
    }

    /** Client profile ids worth checking for reminders (in care in the last 90 days). */
    public static function inCareProfileIds(): Collection
    {
        $since = now()->subDays(90);

        return TherapySession::whereIn('status', self::ACTIVE_STATUSES)
            ->where(fn ($q) => $q->where('scheduled_at', '>=', $since)->orWhere('created_at', '>=', $since))
            ->distinct()
            ->pluck('client_profile_id');
    }
}
