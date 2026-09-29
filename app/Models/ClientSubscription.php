<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSubscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_profile_id',
        'subscription_plan_id',
        'status',
        'sessions_remaining',
        'current_period_start',
        'current_period_end',
    ];

    protected function casts(): array
    {
        return [
            'sessions_remaining' => 'integer',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
        ];
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function hasCreditAvailable(): bool
    {
        return $this->status === 'active' && $this->sessions_remaining > 0;
    }
}
