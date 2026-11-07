<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A therapist sent a library resource to a client (2026-11-07, roadmap 77). */
class ResourceShare extends Model
{
    use HasUuids;

    protected $fillable = ['resource_id', 'client_profile_id', 'therapist_profile_id', 'note', 'opened_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function therapistProfile(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
