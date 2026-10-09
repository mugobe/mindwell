<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client's plan: a bucket of session credits for the current period.
 *
 * Renewals (added 2026-10-01, decided with Collins via AskUserQuestion):
 *  - RESET each period: when a new period starts the credits are set back to the plan's
 *    sessions_included. Unused credits don't carry over.
 *  - 3-DAY GRACE: if the client hasn't renewed by current_period_end ("renews on"),
 *    the plan stays active — remaining credits still bookable — for
 *    config('mindwell.subscription_grace_days') more days, then expires.
 *  - Renewing EARLY just records renewal_paid_at; the switch to the new period (and the
 *    credit reset) happens on current_period_end, so nobody loses what they already
 *    paid for. Renewing during the grace period starts the new period that day.
 *
 * Dates: current_period_end is the day the NEXT period starts, so a weekly plan bought
 * Oct 1 covers Oct 1–7 and "renews on" Oct 8; with 3 grace days it's usable up to and
 * including Oct 10 and expires on Oct 11.
 *
 * sync() applies whatever is due (roll over a prepaid plan, or expire one whose grace
 * is over). It's called from the scheduler AND lazily right before credits are checked
 * (usableFor()), so the rules hold even if the scheduler isn't running.
 */
class ClientSubscription extends Model
{
    use HasUuids;

    public const PHASE_ACTIVE = 'active';          // mid-period, nothing to do
    public const PHASE_ENDING_SOON = 'ending_soon'; // within the reminder window, not renewed yet
    public const PHASE_RENEWED = 'renewed';         // next period already paid for
    public const PHASE_GRACE = 'grace';             // renewal date passed, still usable for a few days
    public const PHASE_ROLL_DUE = 'roll_due';       // prepaid and the renewal date has come — sync() rolls it
    public const PHASE_EXPIRE_DUE = 'expire_due';   // grace is over — sync() expires it
    public const PHASE_ENDED = 'ended';             // status is expired / canceled

    protected $fillable = [
        'client_profile_id',
        'subscription_plan_id',
        'status',
        'sessions_remaining',
        'current_period_start',
        'current_period_end',
        'renewal_paid_at',
        'renewal_reminder_sent_at',
        'renewal_due_sent_at',
        'expired_notice_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sessions_remaining' => 'integer',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'renewal_paid_at' => 'datetime',
            'renewal_reminder_sent_at' => 'datetime',
            'renewal_due_sent_at' => 'datetime',
            'expired_notice_sent_at' => 'datetime',
        ];
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function hasCreditAvailable(): bool
    {
        return $this->status === 'active' && $this->sessions_remaining > 0;
    }

    // ------------------------------------------------------------------
    // Renewal rules

    public static function graceDays(): int
    {
        return max(0, (int) config('mindwell.subscription_grace_days', 3));
    }

    public static function reminderDays(): int
    {
        return max(0, (int) config('mindwell.subscription_reminder_days', 3));
    }

    public function phase(?CarbonInterface $today = null): string
    {
        if ($this->status !== 'active' || ! $this->current_period_end) {
            return self::PHASE_ENDED;
        }

        $today = Carbon::instance($today ?? now())->startOfDay();
        $renewsOn = $this->current_period_end->copy()->startOfDay();

        if ($today->gte($renewsOn)) {
            if ($this->renewal_paid_at) {
                return self::PHASE_ROLL_DUE;
            }

            return $today->lt($renewsOn->copy()->addDays(self::graceDays()))
                ? self::PHASE_GRACE
                : self::PHASE_EXPIRE_DUE;
        }

        if ($this->renewal_paid_at) {
            return self::PHASE_RENEWED;
        }

        return $today->gte($renewsOn->copy()->subDays(self::reminderDays()))
            ? self::PHASE_ENDING_SOON
            : self::PHASE_ACTIVE;
    }

    /** The last day the remaining credits can be used if the client doesn't renew. */
    public function lastUsableDay(): Carbon
    {
        return $this->current_period_end->copy()->startOfDay()->addDays(self::graceDays())->subDay();
    }

    public function canRenew(): bool
    {
        return $this->status === 'active'
            && ! $this->renewal_paid_at
            && $this->subscriptionPlan !== null;
    }

    /**
     * Applies whatever is due. Returns 'rolled', 'expired' or null (nothing happened).
     * Safe to call from several places at once: each change is a conditional UPDATE, so
     * only one caller actually rolls/expires a given period.
     */
    public function sync(?CarbonInterface $today = null): ?string
    {
        $result = null;

        // A few passes in case the scheduler was off for a long time (roll a prepaid
        // period, then find that new period has itself run out too).
        for ($pass = 0; $pass < 3; $pass++) {
            $phase = $this->phase($today);

            if ($phase === self::PHASE_ROLL_DUE) {
                if ($this->rollOver($this->current_period_end, requirePrepaid: true)) {
                    $result = 'rolled';
                }

                continue;
            }

            if ($phase === self::PHASE_EXPIRE_DUE) {
                return $this->expireNow() ? 'expired' : $result;
            }

            break;
        }

        return $result;
    }

    /**
     * Starts a fresh period on $start: credits RESET to the plan's sessions_included,
     * reminder stamps cleared. Used when a prepaid period comes round, and when a
     * client renews during the grace period (then $start is today).
     */
    public function rollOver(CarbonInterface $start, bool $requirePrepaid = false): bool
    {
        $plan = $this->subscriptionPlan;
        if (! $plan || ! $this->current_period_end) {
            return false;
        }

        $start = Carbon::instance($start)->startOfDay();

        $affected = static::whereKey($this->getKey())
            ->where('status', 'active')
            ->whereDate('current_period_end', $this->current_period_end->toDateString())
            ->when($requirePrepaid, fn ($q) => $q->whereNotNull('renewal_paid_at'))
            ->update([
                'current_period_start' => $start->toDateString(),
                'current_period_end' => $plan->periodEndFrom($start)->toDateString(),
                'sessions_remaining' => $plan->sessions_included,
                'renewal_paid_at' => null,
                'renewal_reminder_sent_at' => null,
                'renewal_due_sent_at' => null,
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $affected > 0;
    }

    private function expireNow(): bool
    {
        $affected = static::whereKey($this->getKey())
            ->where('status', 'active')
            ->whereNull('renewal_paid_at')
            ->whereDate('current_period_end', $this->current_period_end->toDateString())
            ->update([
                'status' => 'expired',
                'sessions_remaining' => 0,
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $affected > 0;
    }

    /** Brings every active plan of one client up to date. */
    public static function syncFor(string $clientProfileId): void
    {
        static::where('client_profile_id', $clientProfileId)
            ->where('status', 'active')
            ->with('subscriptionPlan')
            ->get()
            ->each(fn (self $subscription) => $subscription->sync());
    }

    /**
     * The plan a booking should take a credit from, or null. Replaces the plain
     * "active and sessions_remaining > 0" query the booking paths used before renewals
     * existed — that one kept honouring plans long after their period had ended.
     */
    public static function usableFor(string $clientProfileId): ?self
    {
        static::syncFor($clientProfileId);

        return static::where('client_profile_id', $clientProfileId)
            ->where('status', 'active')
            ->where('sessions_remaining', '>', 0)
            ->orderByDesc('current_period_start')
            ->first();
    }
}
