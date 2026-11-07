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
 *  4. otherwise ask the payment service (PAYMENT_GATEWAY: Flutterwave or MarzPay) to
 *     start the Mobile Money charge and record its id.
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
                return ['error' => $quote['error'] ?? 'That voucher has just changed. Please check the price and try again.'];
            }

            return ['transaction' => PaymentTransaction::create(array_merge($base, [
                'status' => 'pending',
                'currency' => $base['currency'] ?? config('flutterwave.currency'),
                'amount' => $quote['total'],
                'tx_ref' => ($free ? 'MW-VOUCHER-' : $prefix).strtoupper(Str::random(10)),
                // MarzPay (2026-11-02): each payment remembers the service that started it,
                // and MarzPay needs its own UUID v4 reference per attempt.
                'gateway' => $free ? 'voucher' : self::gateway(),
                'gateway_reference' => (! $free && self::gateway() === 'marzpay') ? MarzPayClient::uuid4() : null,
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
        // Remember the network and number for this browser only, so "try again" after a
        // failed payment doesn't make the client type them again (2026-11-03). Never saved
        // to the database; the app keeps its own.
        if (request()->hasSession()) {
            request()->session()->put('mw_last_momo', ['network' => $momo['network'] ?? null, 'phone_number' => $momo['phone_number'] ?? null]);
        }

        if ($transaction->gateway === 'marzpay') {
            return $this->startMarzPay($transaction, $momo);
        }

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

    /** Which service starts new payments: PAYMENT_GATEWAY (config/payments.php). */
    public static function gateway(): string
    {
        return config('payments.gateway') === 'marzpay' ? 'marzpay' : 'flutterwave';
    }

    /**
     * Step 4 with MarzPay (2026-11-02). Same return shape as the Flutterwave branch, so
     * the website and the API carry on to the same waiting page / status polling.
     */
    private function startMarzPay(PaymentTransaction $transaction, array $momo): array
    {
        $amount = (int) round((float) $transaction->amount);
        $min = (int) config('marzpay.min_amount', 500);
        $max = (int) config('marzpay.max_amount', 10000000);

        if ($amount < $min || $amount > $max) {
            $transaction->update(['status' => 'failed']);

            return ['ok' => false, 'error' => $amount < $min
                ? 'Mobile Money payments must be at least '.$transaction->currency.' '.number_format($min).'. Nothing was charged.'
                : 'This amount is above the Mobile Money limit. Please contact us to pay another way.'];
        }

        $phone = MarzPayClient::e164($momo['phone_number']);
        if (! $phone) {
            $transaction->update(['status' => 'failed']);

            return ['ok' => false, 'error' => 'Enter a valid Ugandan mobile number, e.g. 0772123456.'];
        }

        // Older rows (or a gateway switch mid-checkout) may not have one yet.
        if (! $transaction->gateway_reference) {
            $transaction->update(['gateway_reference' => MarzPayClient::uuid4()]);
        }

        try {
            $started = app(MarzPayClient::class)->collect(
                reference: $transaction->gateway_reference,
                amount: $amount,
                phone: $phone,
                description: (string) config('marzpay.description', 'Mindwell Consultancy payment'),
                // Only our own ids: nothing personal, nothing about health.
                metadata: [
                    ['mindwell_payment' => (string) $transaction->id],
                    ['purpose' => (string) $transaction->purpose],
                ],
                callbackUrl: self::marzPayCallbackUrl(),
            );
        } catch (MarzPayException $e) {
            $transaction->update(['status' => 'failed']);

            Log::warning('MarzPay collection could not be started', [
                'payment_transaction_id' => $transaction->id,
                'reference' => $transaction->gateway_reference,
                'http' => $e->httpStatus,
                'error_code' => $e->errorCode,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->userMessage()];
        } catch (Throwable $e) {
            $transaction->update(['status' => 'failed']);
            Log::warning('MarzPay collection could not be started', ['payment_transaction_id' => $transaction->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => "We couldn't reach the payment service just now, so nothing was charged. Please check your connection and try again in a moment."];
        }

        $instruction = 'Check your phone and enter your Mobile Money PIN to approve the payment.';

        $transaction->update([
            'flw_transaction_id' => $started['uuid'], // the service's own transaction id
            'meta' => [
                'gateway' => 'marzpay',
                'instruction' => $instruction,
                'marzpay_status' => $started['status'],
                // Remembered per payment, so receipts stay right after the account goes live.
                'sandbox' => $started['sandbox'] || (bool) config('marzpay.sandbox'),
            ],
        ]);

        return ['ok' => true, 'instruction' => $instruction, 'redirect_url' => null];
    }

    /** MarzPay only posts to public HTTPS, so a local http:// address sends none. */
    public static function marzPayCallbackUrl(): ?string
    {
        $url = config('marzpay.callback_url') ?: route('webhooks.marzpay');

        return str_starts_with((string) $url, 'https://') ? $url : null;
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
