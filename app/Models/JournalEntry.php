<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's journal entry — either their notepad from one video session
 * (kind 'session') or a daily mood entry (kind 'daily'). Private to the client unless
 * they turn on shared_with_therapist for that entry. See the create_journal_entries
 * migration for the full reasoning.
 */
class JournalEntry extends Model
{
    use HasUuids;

    public const MOODS = [
        1 => 'Very low',
        2 => 'Low',
        3 => 'Okay',
        4 => 'Good',
        5 => 'Great',
    ];

    protected $fillable = [
        'client_profile_id',
        'therapy_session_id',
        'kind',
        'mood',
        'body',
        'shared_with_therapist',
    ];

    protected function casts(): array
    {
        return [
            'mood' => 'integer',
            'shared_with_therapist' => 'boolean',
        ];
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function therapySession(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class);
    }

    public function moodLabel(): ?string
    {
        return $this->mood ? (self::MOODS[$this->mood] ?? null) : null;
    }
}
