<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Session extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'therapist_id',
        'topic',
        'scheduled_at',
        'duration_minutes',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class, 'client_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_id');
    }

    public function videoRoom(): HasOne
    {
        return $this->hasOne(VideoRoom::class, 'session_id');
    }

    public function sessionNote(): HasOne
    {
        return $this->hasOne(SessionNote::class, 'session_id');
    }

    public function crisisFlags(): HasMany
    {
        return $this->hasMany(CrisisFlag::class, 'session_id');
    }

    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class, 'session_id');
    }
}