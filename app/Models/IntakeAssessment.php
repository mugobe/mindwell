<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeAssessment extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'responses',
        'phq9_score',
        'gad7_score',
        'risk_level',
    ];

    protected function casts(): array
    {
        return [
            'responses' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class, 'client_id');
    }
}