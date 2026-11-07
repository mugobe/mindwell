<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A therapy goal (2026-11-07, roadmap 39). The therapist sets it with the client; the
 * client moves the progress bar and can add a note; the therapist marks it achieved or
 * stops it. Every change is kept in therapy_goal_updates.
 */
class TherapyGoal extends Model
{
    use HasUuids;

    public const STATUSES = ['active' => 'Working on it', 'achieved' => 'Achieved', 'stopped' => 'Stopped'];

    protected $fillable = ['client_profile_id', 'therapist_profile_id', 'title', 'details', 'status', 'progress', 'target_on', 'achieved_at'];

    protected function casts(): array
    {
        return ['progress' => 'integer', 'target_on' => 'date', 'achieved_at' => 'datetime'];
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TherapyGoalUpdate::class)->latest();
    }

    public function therapistProfile(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
