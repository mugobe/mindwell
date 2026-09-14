<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TherapistProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'license_number',
        'license_state',
        'specialties',
        'credentials_verified',
        'hourly_rate',
        'flw_subaccount_id',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'credentials_verified' => 'boolean',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class, 'therapist_id');
    }

    public function clientRelationships(): HasMany
    {
        return $this->hasMany(TherapistClientRelationship::class, 'therapist_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'therapist_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'therapist_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(TherapistPayout::class, 'therapist_id');
    }

    public function groupSessions(): HasMany
    {
        return $this->hasMany(GroupSession::class, 'therapist_id');
    }

    public function matchingResults(): HasMany
    {
        return $this->hasMany(MatchingResult::class, 'therapist_id');
    }
}