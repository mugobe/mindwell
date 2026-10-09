<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One successful use of a voucher (2026-10-07) — written when the payment completes. */
class VoucherRedemption extends Model
{
    use HasUuids;

    protected $fillable = ['voucher_id', 'client_profile_id', 'payment_transaction_id', 'purpose', 'original_amount', 'discount_amount'];

    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2', 'discount_amount' => 'decimal:2'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
