<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    /**
     * When a period of this plan that starts on $start ends (= the day it renews).
     * One place for "weekly vs monthly" (added with renewals, 2026-10-01).
     */
    public function periodEndFrom(CarbonInterface $start): Carbon
    {
        $start = Carbon::instance($start)->startOfDay();

        return $this->billing_interval === 'monthly' ? $start->addMonthNoOverflow() : $start->addWeek();
    }
}
