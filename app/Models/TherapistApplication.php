<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * A therapist's application to join Mindwell (onboarding, 2026-11-03).
 *
 *   draft → submitted → approved
 *                     → changes_requested → submitted → …
 *                     → declined
 *
 * The answers for every step sit in `answers` (JSON), keyed by step:
 *   licence:  profession, profession_other, regulator, regulator_other, licence_number,
 *             licence_issued, licence_expiry
 *   training: qualification_level, qualification_title, institution, qualification_year,
 *             other_qualifications, years_practice, supervision, supervisor_name,
 *             supervisor_contact, specialties[], approaches[], languages[],
 *             languages_other, age_groups[], session_types[]
 *   fitness:  declarations.{key}.answer|details, insurance, insurer, insurance_expiry,
 *             references[0..1].name|role|organisation|relationship|email|phone
 *   profile:  professional_title, bio, fee, photo_path
 * Consents live in consent_records, documents in therapist_application_documents.
 * The questions themselves are in config/onboarding.php.
 */
class TherapistApplication extends Model
{
    use HasUuids;

    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const CHANGES = 'changes_requested';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    protected $fillable = [
        'user_id', 'therapist_profile_id', 'status', 'answers', 'checklist', 'decision_note',
        'decided_by', 'submitted_at', 'decided_at', 'submissions',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'checklist' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function therapistProfile(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TherapistApplicationDocument::class);
    }

    public function document(string $kind): ?TherapistApplicationDocument
    {
        return $this->documents->firstWhere('kind', $kind);
    }

    /** answers['licence']['regulator'] → $app->answer('licence.regulator') */
    public function answer(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->answers ?? [], $path, $default);
    }

    public function setStep(string $step, array $values): void
    {
        $answers = $this->answers ?? [];
        $answers[$step] = array_merge($answers[$step] ?? [], $values);
        $this->answers = $answers;
    }

    /** The applicant can still change their answers. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::CHANGES], true);
    }

    public function statusLabel(): string
    {
        return [
            self::DRAFT => 'Not submitted yet',
            self::SUBMITTED => 'Under review',
            self::CHANGES => 'Changes requested',
            self::APPROVED => 'Approved',
            self::DECLINED => 'Not approved',
        ][$this->status] ?? ucfirst($this->status);
    }

    /** mw-chip colour for the status. */
    public function statusTone(): string
    {
        return [
            self::DRAFT => 'grey',
            self::SUBMITTED => 'navy',
            self::CHANGES => 'amber',
            self::APPROVED => 'green',
            self::DECLINED => 'red',
        ][$this->status] ?? 'grey';
    }

    public function label(string $list, ?string $key, ?string $otherPath = null): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }
        if ($key === 'other' && $otherPath) {
            return $this->answer($otherPath) ?: 'Other';
        }

        return config('onboarding.'.$list.'.'.$key, $key);
    }

    public function professionLabel(): ?string
    {
        return $this->label('professions', $this->answer('licence.profession'), 'licence.profession_other');
    }

    public function regulatorLabel(): ?string
    {
        return $this->label('regulators', $this->answer('licence.regulator'), 'licence.regulator_other');
    }

    public function licenceExpiry(): ?Carbon
    {
        $v = $this->answer('licence.licence_expiry');

        return $v ? rescue(fn () => Carbon::parse($v), null, false) : null;
    }

    /** Declarations answered "yes", for the review screen. */
    public function flaggedDeclarations(): array
    {
        $out = [];
        foreach (config('onboarding.declarations') as $key => $q) {
            if ($this->answer('fitness.declarations.'.$key.'.answer') === 'yes') {
                $out[$key] = ['question' => $q, 'details' => $this->answer('fitness.declarations.'.$key.'.details')];
            }
        }

        return $out;
    }

    /**
     * What is still missing, per step, in plain words. An empty array means the
     * application can be submitted.
     *
     * @return array<string, array<int, string>>
     */
    public function missing(): array
    {
        $m = [];
        $need = function (string $step, bool $ok, string $what) use (&$m) {
            if (! $ok) {
                $m[$step][] = $what;
            }
        };
        $has = fn (string $path) => filled($this->answer($path));

        // 1. Licence
        $need('licence', $has('licence.profession'), 'Your profession');
        $need('licence', $this->answer('licence.profession') !== 'other' || $has('licence.profession_other'), 'The name of your profession');
        $need('licence', $has('licence.regulator'), 'Who registers you');
        $need('licence', $this->answer('licence.regulator') !== 'other' || $has('licence.regulator_other'), 'The name of the body that registers you');
        $need('licence', $has('licence.licence_number'), 'Your licence or registration number');
        $need('licence', $has('licence.licence_expiry'), 'When your licence expires');
        $expiry = $this->licenceExpiry();
        $need('licence', ! $expiry || $expiry->isFuture(), 'A licence that is still in date');

        // 2. Training
        foreach (['qualification_level' => 'Your highest qualification', 'qualification_title' => 'The name of your qualification', 'institution' => 'Where you trained', 'qualification_year' => 'The year you qualified'] as $k => $what) {
            $need('training', $has('training.'.$k), $what);
        }
        $need('training', $this->answer('training.years_practice') !== null && $this->answer('training.years_practice') !== '', 'Years in practice');
        $need('training', in_array($this->answer('training.supervision'), ['yes', 'no'], true), 'Whether you receive clinical supervision');
        $need('training', count((array) $this->answer('training.specialties', [])) > 0, 'At least one area you work with');
        $need('training', count((array) $this->answer('training.approaches', [])) > 0, 'At least one therapy approach');
        $need('training', count((array) $this->answer('training.languages', [])) > 0 || $has('training.languages_other'), 'The languages you offer sessions in');
        $need('training', count((array) $this->answer('training.age_groups', [])) > 0, 'Who you work with');
        $need('training', count((array) $this->answer('training.session_types', [])) > 0, 'Video, in person or both');

        // 3. Fitness to practise
        foreach (array_keys(config('onboarding.declarations')) as $key) {
            $a = $this->answer('fitness.declarations.'.$key.'.answer');
            $need('fitness', in_array($a, ['yes', 'no'], true), 'An answer to every declaration');
            if ($a === 'yes') {
                $need('fitness', $has('fitness.declarations.'.$key.'.details'), 'Details for each "yes" answer');
            }
        }
        $need('fitness', in_array($this->answer('fitness.insurance'), ['yes', 'no'], true), 'Whether you have indemnity insurance');
        for ($i = 0; $i < 2; $i++) {
            $ok = $has("fitness.references.$i.name") && $has("fitness.references.$i.relationship") && ($has("fitness.references.$i.email") || $has("fitness.references.$i.phone"));
            $need('fitness', $ok, 'Two references, each with a name, how they know you, and an email or phone');
        }

        // 4. Profile
        $need('profile', $has('profile.professional_title'), 'Your professional title');
        $need('profile', mb_strlen((string) $this->answer('profile.bio')) >= 80, 'A short bio (at least 80 characters)');
        $need('profile', $has('profile.photo_path'), 'A profile photo');

        // Documents
        foreach (config('onboarding.documents') as $kind => $doc) {
            if ($doc['required']) {
                $need($doc['step'], (bool) $this->document($kind), 'Upload: '.$doc['label']);
            }
        }

        // 5. Consents
        if ($this->user) {
            foreach (ConsentRecord::currentFor($this->user) as $key => $rec) {
                $need('consents', (bool) $rec, 'Agree to the '.strtolower(config('onboarding.consents.'.$key.'.title')));
            }
        }

        return array_map(fn ($list) => array_values(array_unique($list)), $m);
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    public function checklistDone(string $key): bool
    {
        return (bool) ($this->checklist[$key]['done'] ?? false);
    }

    /** Required checklist items still unticked. */
    public function checklistOutstanding(): array
    {
        return collect(config('onboarding.checklist'))
            ->filter(fn ($item, $key) => $item['required'] && ! $this->checklistDone($key))
            ->map(fn ($item) => $item['label'])
            ->values()
            ->all();
    }

    /** The applicant's application, created on first visit. */
    public static function forUser(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            ['therapist_profile_id' => $user->therapistProfile?->id, 'status' => self::DRAFT, 'answers' => []],
        );
    }
}
