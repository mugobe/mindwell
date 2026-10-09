<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A therapist's blocked time (2026-10-08, roadmap 75). Asked for by the therapist,
 * approved by admin (Collins). Only APPROVED time off hides booking slots.
 */
class TimeOff extends Model
{
    use HasUuids;

    protected $table = 'therapist_time_off';

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['therapist_profile_id', 'starts_at', 'ends_at', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function therapistProfile(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    /** Approved time off for a therapist that overlaps [$from, $to). */
    public static function blocking(string $therapistProfileId, $from, $to)
    {
        return static::where('therapist_profile_id', $therapistProfileId)
            ->where('status', self::APPROVED)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from);
    }

    /** Confirmed or waiting sessions that fall inside this time off. */
    public function clashingSessions()
    {
        return TherapySession::with('clientProfile.user')
            ->where('therapist_profile_id', $this->therapist_profile_id)
            ->whereIn('status', ['scheduled', 'pending_approval'])
            ->where('scheduled_at', '<', $this->ends_at)
            ->where('scheduled_at', '>', $this->starts_at->copy()->subMinutes((int) config('mindwell.session_duration_minutes', 70)))
            ->orderBy('scheduled_at')
            ->get();
    }

    /** "Mon 13 Oct, 9:00 AM – 5:00 PM" or across days. */
    public function rangeLabel(): string
    {
        return $this->starts_at->isSameDay($this->ends_at)
            ? $this->starts_at->format('D j M, g:i A').' – '.$this->ends_at->format('g:i A')
            : $this->starts_at->format('D j M, g:i A').' – '.$this->ends_at->format('D j M, g:i A');
    }
}
