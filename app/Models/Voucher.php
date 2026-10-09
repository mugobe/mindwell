<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code (2026-10-07, roadmap 25). All the rules live on the row and are edited
 * under Admin → Vouchers; App\Services\Vouchers applies them at checkout. A use is only
 * counted once the payment actually goes through (VoucherRedemption).
 */
class Voucher extends Model
{
    use HasUuids;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    public const FOR_SESSION = 'session';
    public const FOR_PLAN = 'plan';
    public const FOR_RENEWAL = 'renewal';

    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value', 'max_discount', 'min_amount',
        'applies_to_sessions', 'applies_to_plans', 'applies_to_renewals',
        'new_clients_only', 'client_user_id', 'max_uses_total', 'max_uses_per_client',
        'starts_at', 'ends_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'applies_to_sessions' => 'boolean',
            'applies_to_plans' => 'boolean',
            'applies_to_renewals' => 'boolean',
            'new_clients_only' => 'boolean',
            'max_uses_total' => 'integer',
            'max_uses_per_client' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeCode(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appliesTo(string $purpose): bool
    {
        return match ($purpose) {
            self::FOR_SESSION => $this->applies_to_sessions,
            self::FOR_PLAN => $this->applies_to_plans,
            self::FOR_RENEWAL => $this->applies_to_renewals,
            default => false,
        };
    }

    /** How much comes off $amount (never more than $amount). */
    public function discountOn(float $amount): float
    {
        $discount = $this->discount_type === self::TYPE_PERCENT
            ? round($amount * min(100, (float) $this->discount_value) / 100)
            : (float) $this->discount_value;

        if ($this->discount_type === self::TYPE_PERCENT && $this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return max(0, min($amount, $discount));
    }

    /** "20% off" / "UGX 30,000 off" */
    public function discountLabel(): string
    {
        if ($this->discount_type === self::TYPE_PERCENT) {
            $label = rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.').'% off';

            return $this->max_discount !== null
                ? $label.' (up to '.config('flutterwave.currency').' '.number_format((float) $this->max_discount, 0).')'
                : $label;
        }

        return config('flutterwave.currency').' '.number_format((float) $this->discount_value, 0).' off';
    }

    /** "Sessions, plans" */
    public function appliesLabel(): string
    {
        return collect([
            $this->applies_to_sessions ? 'Sessions' : null,
            $this->applies_to_plans ? 'New plans' : null,
            $this->applies_to_renewals ? 'Renewals' : null,
        ])->filter()->join(', ') ?: 'Nothing';
    }

    /** Live / Scheduled / Expired / Used up / Off — for the admin list. */
    public function statusLabel(?int $uses = null): array
    {
        $uses ??= $this->redemptions()->count();

        return match (true) {
            ! $this->is_active => ['', 'Switched off'],
            $this->ends_at && $this->ends_at->isPast() => ['', 'Expired'],
            $this->starts_at && $this->starts_at->isFuture() => ['warn', 'Starts '.$this->starts_at->format('j M')],
            $this->max_uses_total !== null && $uses >= $this->max_uses_total => ['', 'Used up'],
            default => ['success', 'Live'],
        };
    }
}
