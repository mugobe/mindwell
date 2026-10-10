<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Starting a payment — shared by the website and the mobile API (2026-10-09, API v1).
 *
 * Before this, SessionRequestController::pay() and SubscriptionController::payWithVoucher()
 * each held their own copy of the same steps. They now both call this, and so do the
 * API controllers, so the app and the website can never drift apart on money:
 *
 *  1. preview the voucher (never trusted from an earlier quote);
 *  2. while holding a lock on the voucher, re-check it and create the PaymentTransaction
 *     (so a voucher's last use can't be taken twice);
 *  3. a voucher that covers the whole price skips Mobile Money entirely ("free");
 *  4. otherwise ask Flutterwave to start the Mobile Money charge and record its id.
 *
 * Nothing here changes a session or a plan — that still only happens in
 * FlutterwavePaymentController::finalize() once the payment is confirmed.
 */
class Checkout
{
    public const MOMO_RULES = [
        'network' => ['required', 'in:MTN,AIRTEL'],
        'phone_number' => ['required', 'regex:/^(0|256)?7[0-9]{8}$/'],
    ];

    public const MOMO_MESSAGES = [
        'phone_number.regex' => 'Enter a valid Ugandan mobile number, e.g. 0772123456.',
    ];

    public function __construct(private Vouchers $vouchers) {}

    /**
     * Steps 1–3. Returns one of:
     *  ['error' => string]                                   voucher problem, nothing created
     *  ['transaction' => PaymentTransaction, 'free' => bool] created, status 'pending'
     *
     * $momo is ['network' => .., 'phone_number' => ..] (already validated) or null when
     * the caller knows the voucher makes it free. $base holds the purpose-specific
     * columns (client_profile_id, purpose, therapy_session_id / subscription_plan_id …).
     */
    public function createTransaction(?string $code, string $voucherPurpose, float $price, $profile, array $target, array $base, string $prefix, ?array $momo): array
    {
        $hasCode = filled($code);

        $preview = $this->vouchers->quote($code, $voucherPurpose, $price, $profile, $target);
        if ($hasCode && ! $preview['ok']) {
            return ['error' => $preview['error']];
        }

        $free = $preview['applied'] && $preview['total'] <= 0;

        if (! $free && ! $momo) {
            return ['error' => 'Choose MTN or Airtel and enter your Mobile Money number.'];
        }

        $result = $this->vouchers->withLock($code, function () use ($code, $hasCode, $voucherPurpose, $price, $profile, $target, $free, $momo, $base, $prefix) {
            $quote = $this->vouchers->quote($code, $voucherPurpose, $price, $profile, $target);
            if (($hasCode && ! $quote['ok']) || (($quote['applied'] && $quote['total'] <= 0) !== $free)) {
                return ['error' => $quote['error'] ?? 'That voucher has just changed — please check the price and try again.'];
            }

            return ['transaction' => PaymentTransaction::create(array_merge($base, [
                'status' => 'pending',
                'currency' => $base['currency'] ?? config('flutterwave.currency'),
                'amount' => $quote['total'],
                'tx_ref' => ($free ? 'MW-VOUCHER-' : $prefix).strtoupper(Str::random(10)),
                'method_label' => $free
                    ? 'Voucher '.$quote['voucher']->code
                    : PaymentTransaction::methodLabelFor($momo['network'], $momo['phone_number']),
            ], Vouchers::transactionFields($quote)))];
        });

        if (isset($result['error'])) {
            return $result;
        }

        return ['transaction' => $result['transaction'], 'free' => $free];
    }

    /**
     * Step 4. Returns ['ok' => true, 'instruction' => ?string, 'redirect_url' => ?string]
     * or ['ok' => false, 'error' => string] (the transaction is then marked failed).
     */
    public function startMobileMoney(PaymentTransaction $transaction, array $momo, $user): array
    {
        try {
            $charge = app(FlutterwaveClient::class)->initiateMobileMoneyCharge(
                reference: $transaction->tx_ref,
                amount: (float) $transaction->amount,
                currency: $transaction->currency,
                network: $momo['network'],
                phoneNumber: self::normalizePhoneNumber($momo['phone_number']),
                customer: [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
            );
        } catch (Throwable $e) {
            $transaction->update(['status' => 'failed']);

            // The technical reason (e.g. "cURL error 6: Could not resolve host") goes to the
            // log for us, never to the client (2026-10-09, seen on the app's pay screen).
            Log::warning('Mobile Money charge could not be started', [
                'payment_transaction_id' => $transaction->id,
                'tx_ref' => $transaction->tx_ref,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => "We couldn't reach the payment service just now, so nothing was charged. Please check your connection and try again in a moment."];
        }

        // Recorded immediately (before the client approves anything) so every later check
        // has a charge id to verify against.
        $transaction->update([
            'flw_transaction_id' => $charge['id'],
            'meta' => ['instruction' => $charge['instruction']],
        ]);

        return ['ok' => true, 'instruction' => $charge['instruction'] ?? null, 'redirect_url' => $charge['redirect_url'] ?? null];
    }

    /**
     * The LOCAL 9-digit number Flutterwave's v4 mobile_money schema expects (no leading 0,
     * no country code — confirmed against the sandbox 2026-09-28).
     */
    public static function normalizePhoneNumber(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber);

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            return substr($digits, 3);
        }

        return str_starts_with($digits, '0') ? substr($digits, 1) : $digits;
    }
}
