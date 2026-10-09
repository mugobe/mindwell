<?php

namespace App\Models;

use App\Support\IntakeQuestions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * A client's "About you" answers from intake (2026-10-09, roadmap 63). One row per client.
 * Seen by their therapist and the admin team only; never used for matching (Collins).
 * See App\Support\IntakeQuestions for the sections and the admin on/off switches.
 */
class ClientIntakeDetail extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_profile_id', 'topics', 'own_words', 'mood_now', 'counselling_before', 'medication',
        'background_notes', 'goals', 'goals_text', 'pref_therapist_gender', 'pref_language', 'pref_mode',
        'skipped', 'source', 'completed_at', 'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'goals' => 'array',
            'skipped' => 'array',
            'mood_now' => 'integer',
            'completed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    /** @var array<string, ?self> per-request memo, so lists don't query twice per client */
    private static array $memo = [];

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public static function tableReady(): bool
    {
        static $ready = null;

        return $ready ??= rescue(fn () => Schema::hasTable('client_intake_details'), false, false);
    }

    public static function for(?string $clientProfileId): ?self
    {
        if (! $clientProfileId || ! self::tableReady()) {
            return null;
        }

        // array_key_exists, not ??=, so "this client has no row" is remembered too.
        if (! array_key_exists($clientProfileId, self::$memo)) {
            self::$memo[$clientProfileId] = static::where('client_profile_id', $clientProfileId)->first();
        }

        return self::$memo[$clientProfileId];
    }

    /** Load many at once (for list pages) and keep them in the memo. */
    public static function preload(iterable $clientProfileIds): void
    {
        if (! self::tableReady()) {
            return;
        }
        $ids = collect($clientProfileIds)->filter()->unique()->reject(fn ($id) => array_key_exists($id, self::$memo))->values();
        if ($ids->isEmpty()) {
            return;
        }
        $rows = static::whereIn('client_profile_id', $ids->all())->get()->keyBy('client_profile_id');
        foreach ($ids as $id) {
            self::$memo[$id] = $rows[$id] ?? null;
        }
    }

    public static function forget(?string $clientProfileId): void
    {
        unset(self::$memo[$clientProfileId]);
    }

    /** Has anything at all been answered? */
    public function hasAnswers(): bool
    {
        foreach (['topics', 'own_words', 'mood_now', 'counselling_before', 'medication', 'background_notes', 'goals', 'goals_text', 'pref_language'] as $f) {
            if (filled($this->{$f})) {
                return true;
            }
        }

        return in_array($this->pref_therapist_gender, ['female', 'male'], true) || in_array($this->pref_mode, ['video', 'in_person'], true);
    }

    public function wasSkipped(string $section): bool
    {
        return in_array($section, (array) $this->skipped, true);
    }

    public function moodLabel(): ?string
    {
        return $this->mood_now ? (JournalEntry::MOODS[$this->mood_now] ?? null) : null;
    }

    public function counsellingLabel(): ?string
    {
        return IntakeQuestions::COUNSELLING[$this->counselling_before] ?? null;
    }

    public function medicationLabel(): ?string
    {
        return IntakeQuestions::MEDICATION[$this->medication] ?? null;
    }

    public function preferenceLines(): array
    {
        $out = [];
        if (in_array($this->pref_therapist_gender, ['female', 'male'], true)) {
            $out[] = 'Therapist: '.mb_strtolower(IntakeQuestions::PREF_GENDER[$this->pref_therapist_gender]);
        }
        if (filled($this->pref_language)) {
            $out[] = 'Language: '.$this->pref_language;
        }
        if (in_array($this->pref_mode, ['video', 'in_person'], true)) {
            $out[] = 'Sessions: '.mb_strtolower(IntakeQuestions::PREF_MODE[$this->pref_mode]);
        }

        return $out;
    }

    /** Short "what brings them" line for lists (topics, else own words). */
    public function concernLine(int $limit = 90): ?string
    {
        if (! empty($this->topics)) {
            return \Illuminate\Support\Str::limit(implode(', ', $this->topics), $limit);
        }

        return filled($this->own_words) ? \Illuminate\Support\Str::limit($this->own_words, $limit) : null;
    }
}
