<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to move or cancel a confirmed session (2026-10-08, roadmap 75). Made by the
 * client or therapist and decided by admin — see App\Services\ScheduleChanges. When
 * admin changes a session directly, one of these is written already approved, so every
 * change has the same history.
 */
class ScheduleChangeRequest extends Model
{
    use HasUuids;

    public const TYPE_RESCHEDULE = 'reschedule';
    public const TYPE_CANCEL = 'cancel';

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';
    public const WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'therapy_session_id', 'requested_by', 'requester_role', 'type', 'current_at', 'proposed_at',
        'reason', 'status', 'decided_by', 'decided_at', 'decision_note', 'outcome',
    ];

    protected function casts(): array
    {
        return [
            'current_at' => 'datetime',
            'proposed_at' => 'datetime',
            'decided_at' => 'datetime',
            'outcome' => 'array',
        ];
    }

    public function therapySession(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** "Move to Tue 14 Oct, 10:00 AM" / "Cancel" */
    public function summary(): string
    {
        return $this->type === self::TYPE_RESCHEDULE
            ? 'Move to '.($this->proposed_at?->format('D j M, g:i A') ?? '—')
            : 'Cancel';
    }

    public function requesterLabel(): string
    {
        return match ($this->requester_role) {
            'client' => 'Client',
            'therapist' => 'Therapist',
            default => 'Admin',
        };
    }
}
