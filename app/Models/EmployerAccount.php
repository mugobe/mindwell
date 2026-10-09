<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployerAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_name',
        'contact_name',
        'contact_email',
        'seats_purchased',
        'contract_start',
        'contract_renewal_date',
        'status',
        'expiry_warning_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'contract_start' => 'date',
            'contract_renewal_date' => 'date',
            'seats_purchased' => 'integer',
            'expiry_warning_sent_at' => 'datetime',
        ];
    }

    public function seats(): HasMany
    {
        return $this->hasMany(EmployerSeat::class);
    }

    public function activeSeatsCount(): int
    {
        return $this->seats()->where('is_active', true)->whereNotNull('client_profile_id')->count();
    }

    /**
     * Whether this employer's contract currently covers redemptions/usage — added
     * 2026-09-27 for employer seat redemption. `contract_renewal_date` is treated as
     * the current period's expiry (same idea as a subscription's `current_period_end`):
     * once it's in the past, the benefit has lapsed even if nobody's flipped `status`
     * to 'inactive' yet. A null renewal date means an open-ended contract.
     */
    public function isCurrentlyActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return ! $this->contract_renewal_date || $this->contract_renewal_date->isFuture();
    }

    /** How many days before the renewal date admins are warned (2026-10-06, roadmap 27). */
    public const EXPIRY_WARNING_DAYS = 30;

    /** Active, with a renewal date coming up within the warning window. */
    public function isEndingSoon(): bool
    {
        return $this->status === 'active'
            && $this->contract_renewal_date
            && $this->contract_renewal_date->isFuture()
            && $this->contract_renewal_date->lte(now()->addDays(self::EXPIRY_WARNING_DAYS));
    }
}
