<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file uploaded with a therapist application (2026-11-03). Stored on the private
 * `local` disk (storage/app/private/therapist-applications/…), never under public/.
 * Only admins can download it, through Admin\TherapistApplicationController::document().
 */
class TherapistApplicationDocument extends Model
{
    use HasUuids;

    protected $fillable = ['therapist_application_id', 'kind', 'path', 'original_name', 'mime', 'size'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(TherapistApplication::class, 'therapist_application_id');
    }

    public function label(): string
    {
        return config('onboarding.documents.'.$this->kind.'.label', ucfirst(str_replace('_', ' ', $this->kind)));
    }

    public function sizeLabel(): string
    {
        $kb = (int) round(($this->size ?? 0) / 1024);

        return $kb >= 1024 ? number_format($kb / 1024, 1).' MB' : max(1, $kb).' KB';
    }
}
