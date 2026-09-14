<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'mood_rating',
        'content_encrypted',
        'shared_with_therapist',
    ];

    protected function casts(): array
    {
        return [
            'shared_with_therapist' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class, 'client_id');
    }
}