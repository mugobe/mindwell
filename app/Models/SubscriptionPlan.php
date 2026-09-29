<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'price',
        'billing_interval',
        'sessions_included',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sessions_included' => 'integer',
            'features' => 'array',
        ];
    }
}
