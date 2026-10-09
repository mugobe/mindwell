<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\TherapySession;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Applies voucher rules at checkout (2026-10-07, roadmap 25).
 *
 *  quote()            — checks a code against a price; used both to preview on the
 *                       checkout page and again (never trusted) when paying
 *  transactionFields()— what to store on the PaymentTransaction
 *  recordRedemption() — counts a use, called once the payment has gone through
 *                       (FlutterwavePaymentController::finalize), so abandoned or failed
 *                       payments never use up a voucher
 */
class Vouchers
{
    /**
     * @param  'session'|'plan'|'renewal'  $purpose
     * @return array{ok: bool, applied: bool, voucher: ?Voucher, code: ?string, error: ?string, original: float, discount: float, total: float}
     */
    public function quote(?string $code, string $purpose, float $amount, $profile, array $sameTarget = []): array
    {
        $result = [
            'ok' => true, 'applied' => false, 'voucher' => null,
            'code' => null, 'error' => null,
            'original' => $amount, 'discount' => 0.0, 'total' => $amount,
        ];

        $code = Voucher::normalizeCode($code);
        if ($code === '') {
            return $result;
        }

        $result['code'] = $code;
        $fail = fn (string $message) => array_merge($result, ['ok' => false, 'error' => $message]);

        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher || ! $voucher->is_active) {
            return $fail("“{$code}” isn't a valid code.");
        }
        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            return $fail("“{$code}” can be used from ".$voucher->starts_at->format('j M Y').'.');
        }
        if ($voucher->ends_at && $voucher->ends_at->isPast()) {
            return $fail("“{$code}” has expired.");
        }
        if (! $voucher->appliesTo($purpose)) {
            $what = ['session' => 'single sessions', 'plan' => 'plans', 'renewal' => 'plan renewals'][$purpose] ?? 'this';

            return $fail("“{$code}” can't be used for {$what}.");
        }
        if ($voucher->client_user_id && $voucher->client_user_id !== $profile?->user_id) {
            return $fail("“{$code}” belongs to a different account.");
        }
        if ($voucher->min_amount !== null && $amount < (float) $voucher->min_amount) {
            return $fail("“{$code}” is for payments of ".config('flutterwave.currency').' '.number_format((float) $voucher->min_amount, 0).' or more.');
        }
        if ($voucher->new_clients_only && $profile && $this->hasHistory($profile->id)) {
            return $fail("“{$code}” is for new clients only.");
        }
        if ($voucher->max_uses_total !== null && $this->usesOf($voucher, null, $sameTarget) >= $voucher->max_uses_total) {
            return $fail("“{$code}” has been fully used.");
        }
        if ($voucher->max_uses_per_client !== null && $profile
            && $this->usesOf($voucher, $profile->id, $sameTarget) >= $voucher->max_uses_per_client) {
            return $fail("You've already used “{$code}”.");
        }

        $discount = $voucher->discountOn($amount);
        if ($discount <= 0) {
            return $fail("“{$code}” doesn't take anything off this price.");
        }

        return array_merge($result, [
            'applied' => true,
            'voucher' => $voucher,
            'discount' => $discount,
            'total' => max(0, round($amount - $discount, 2)),
        ]);
    }

    /**
     * Uses so far = completed uses + payments with this voucher still waiting on the
     * client's Mobile Money approval (so two half-finished payments can't both slip
     * under a limit). An abandoned payment stops counting after PENDING_HOLD_MINUTES.
     */
    public const PENDING_HOLD_MINUTES = 30;

    /** @param  array  $sameTarget  e.g. ['therapy_session_id' => …]: a retry for the same thing isn't a second use */
    public function usesOf(Voucher $voucher, ?string $clientProfileId = null, array $sameTarget = []): int
    {
        $done = $voucher->redemptions()
            ->when($clientProfileId, fn ($q) => $q->where('client_profile_id', $clientProfileId))
            ->count();

        $pending = PaymentTransaction::where('voucher_id', $voucher->id)
            ->where('status', 'pending')
            ->whereNull('processed_at')
            ->where('created_at', '>=', now()->subMinutes(self::PENDING_HOLD_MINUTES))
            ->when($clientProfileId, fn ($q) => $q->where('client_profile_id', $clientProfileId))
            ->when($sameTarget, fn ($q) => $q->whereNot(fn ($w) => $w->where($sameTarget)))
            ->count();

        return $done + $pending;
    }

    /**
     * Run $work (re-quote + create the payment) while holding a lock on the voucher row,
     * so two checkouts at the same moment can't both use its last remaining use.
     */
    public function withLock(?string $code, \Closure $work): mixed
    {
        $code = Voucher::normalizeCode($code);

        return DB::transaction(function () use ($code, $work) {
            if ($code !== '') {
                Voucher::where('code', $code)->lockForUpdate()->first();
            }

            return $work();
        });
    }

    /** Columns for PaymentTransaction::create(). Empty when no voucher was applied. */
    public static function transactionFields(array $quote): array
    {
        if (! ($quote['applied'] ?? false)) {
            return [];
        }

        return [
            'voucher_id' => $quote['voucher']->id,
            'original_amount' => $quote['original'],
            'discount_amount' => $quote['discount'],
        ];
    }

    public function recordRedemption(PaymentTransaction $transaction): void
    {
        if (! $transaction->voucher_id || VoucherRedemption::where('payment_transaction_id', $transaction->id)->exists()) {
            return;
        }

        rescue(fn () => VoucherRedemption::create([
            'voucher_id' => $transaction->voucher_id,
            'client_profile_id' => $transaction->client_profile_id,
            'payment_transaction_id' => $transaction->id,
            'purpose' => $transaction->purpose === 'session' ? Voucher::FOR_SESSION
                : ($transaction->isRenewal() ? Voucher::FOR_RENEWAL : Voucher::FOR_PLAN),
            'original_amount' => $transaction->original_amount ?? $transaction->amount,
            'discount_amount' => $transaction->discount_amount ?? 0,
        ]));
    }

    /** "New client" = has never completed a payment and never had a session completed. */
    private function hasHistory(string $clientProfileId): bool
    {
        return PaymentTransaction::where('client_profile_id', $clientProfileId)->where('status', 'successful')->exists()
            || TherapySession::where('client_profile_id', $clientProfileId)->where('status', 'completed')->exists();
    }
}
