<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An external hospital/clinic/specialist the routing team can refer a client out to
 * (2026-09-26). `facility_type` is free text on purpose -- see the migration.
 */
class Facility extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'facility_type',
        'address',
        'phone',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(TherapySession::class, 'referral_facility_id');
    }
}
