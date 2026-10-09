<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place Mindwell sees clients in person (2026-10-07, roadmap 68). Managed by admin
 * under Admin → Venues; picked when an in-person booking is approved. Not the same as
 * Facility, which is an outside hospital/clinic a client is referred OUT to.
 */
class Venue extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'address', 'directions', 'map_url', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TherapySession::class);
    }

    /** "Name, address" — one line for lists and SMS-free emails. */
    public function label(): string
    {
        return $this->address ? $this->name.', '.$this->address : $this->name;
    }
}
