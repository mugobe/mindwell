<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployerSeat extends Model
{
    use HasUuids;

    protected $fillable = [
        'employer_account_id',
        'client_profile_id',
        'seat_code',
        'is_active',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'assigned_at' => 'datetime',
        ];
    }

    public function employerAccount(): BelongsTo
    {
        return $this->belongsTo(EmployerAccount::class);
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    /**
     * The client's currently-usable employer seat, if any -- added 2026-09-27 for
     * employer seat redemption. A client can hold at most one assigned seat in
     * practice (EmployerSeatController::redeem() blocks redeeming a second one while
     * one is already active), but this defensively checks all of them rather than
     * assuming that invariant holds. Returns null if the seat itself was deactivated
     * by an admin, or if the employer's contract has lapsed (see
     * EmployerAccount::isCurrentlyActive()) -- covered sessions should stop being free
     * the moment either of those happens, without anyone needing to touch this row.
     */
    public static function activeFor(string $clientProfileId): ?self
    {
        return static::where('client_profile_id', $clientProfileId)
            ->where('is_active', true)
            ->with('employerAccount')
            ->get()
            ->first(fn (self $seat) => $seat->employerAccount && $seat->employerAccount->isCurrentlyActive());
    }
}
