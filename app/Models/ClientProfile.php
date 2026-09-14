<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'dob',
        'timezone',
        'current_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'current_subscription_id');
    }

    public function intakeAssessments(): HasMany
    {
        return $this->hasMany(IntakeAssessment::class, 'client_id');
    }

    public function matchingResults(): HasMany
    {
        return $this->hasMany(MatchingResult::class, 'client_id');
    }

    public function therapistRelationships(): HasMany
    {
        return $this->hasMany(TherapistClientRelationship::class, 'client_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'client_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'client_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'client_id');
    }
}