<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change to a goal: new progress and/or a note, by the client or the therapist. */
class TherapyGoalUpdate extends Model
{
    use HasUuids;

    protected $fillable = ['therapy_goal_id', 'by_role', 'progress', 'note'];

    protected function casts(): array
    {
        return ['progress' => 'integer'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(TherapyGoal::class, 'therapy_goal_id');
    }
}
