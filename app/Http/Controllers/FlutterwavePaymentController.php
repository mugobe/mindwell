<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\TherapySession;
use App\Services\FlutterwaveClient;
use App\Services\MarzPayClient;
use App\Services\MarzPayException;
use App\Services\Receipts;
use App\Services\Vouchers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Real Flutterwave integration — REBUILT 2026-09-27 (same day as the first version)
 * after discovering, by testing directly against the real sandbox, that this account
 * only has v4-style OAuth2 credentials, not the classic v3 secret key the first
 * version assumed. v4 has no equivalent of v3's fully-hosted "Standard" checkout, so
 * this currently covers MOBILE MONEY ONLY — see App\Services\FlutterwaveClient and the
 * project doc's "Real Flutterwave integration" section for the full story and what's
 * deferred (card support).
 *
 * SessionRequestController::pay() and SubscriptionController::subscribe() still create
 * the PaymentTransaction and kick off the charge — everything that happens once
 * Flutterwave reports back lives here, in two entry points sharing one finalize():
 *
 *  - callback(): the client's own browser lands here after approving (or declining)
 *    the Mobile Money prompt. Works fine on a local, non-public URL.
 *  - webhook(): Flutterwave's server-to-server POST — built for completeness but
 *    UNREACHABLE until this app has a public URL; see routes/web.php's comment for
 *    the one manual step (a CSRF exception) still needed first.
 *
 * Design choice worth flagging: unlike the first (v3-based) version of this file,
 * which could trust a `?status=...` query param Flutterwave added to the redirect,
 * v4's actual redirect query shape was never confirmed against real documentation.
 * So callback() deliberately trusts NOTHING from the query string except our OWN
 * tx_ref (which WE embedded in the redirect_url when initiating the charge) — the
 * real outcome always comes from calling FlutterwaveClient::verifyCharge() fresh,
 * exactly like webhook() does. The two entry points are fully symmetric here.
 *
 * MarzPay (2026-11-02): payments started with PAYMENT_GATEWAY=marzpay go through the
 * same callback()/waiting()/checkForApi()/finalize() steps. Only the "ask the service
 * what happened" step differs (verify()), and MarzPay's own server-to-server callback
 * is marzpayWebhook(). The class keeps its old name so no route or app call changes.
 */
class FlutterwavePaymentController extends Controller
{
    public function callback(Request $request): RedirectResponse
    {
        $txRef = $request->query('tx_ref');
        $transaction = $txRef ? PaymentTransaction::where('tx_ref', $txRef)->first() : null;

        if (! $transaction) {
            return redirect()->route('dashboard')
                ->with('error', "We couldn't find that payment. If you were charged, contact support.");
        }

        abort_unless(
            $transaction->client_profile_id === $request->user()->clientProfile?->id,
            403
        );

        if ($transaction->processed_at) {
            // Already finalized — most likely the webhook beat this redirect to it, or
            // the client hit back/refresh on this same callback URL.
            return $this->redirectAfter($transaction);
        }

        if (! $transaction->flw_transaction_id) {
            // Flutterwave's charge id was never recorded — shouldn't happen if pay()/
            // subscribe() got far enough to redirect here, but guards against a stray
            // or forged tx_ref showing up on this route.
            return $this->redirectToCheckout($transaction)
                ->with('error', 'Payment could not be confirmed. Please try again.');
        }

        try {
            $verified = $this->verify($transaction);
        } catch (Throwable $e) {
            Log::warning('Payment verify failed on callback', ['tx_ref' => $txRef, 'gateway' => $transaction->gateway, 'error' => $e->getMessage()]);

            // Back to the waiting page, not checkout — the charge itself may be fine;
            // it's only OUR check that failed, and a new payment from checkout could
            // end up charging the client twice.
            return redirect()->route('payments.waiting', $transaction)
                ->with('error', "We couldn't reach the payment service to check this payment just now. We'll keep trying.");
        }

        // Kept until the first real test confirms the field names verifyCharge()
        // assumes are correct — Flutterwave's own v4 docs don't publish a full worked
        // example of this response.
        Log::info('Payment verify response (callback)', ['tx_ref' => $txRef, 'gateway' => $transaction->gateway, 'verified' => $verified]);

        $outcome = $this->classifyStatus($verified);

        if ($outcome === 'pending') {
            // Not a failure — the client just hasn't approved it on their phone yet (or
            // Mobile Money is still processing). Leave the transaction as-is so the
            // waiting page's next check, or an eventual webhook, can pick it up.
            // 2026-11-04: ...but only until the deadline. See closeUnpaid().
            if ($transaction->status === 'pending' && now()->gte($transaction->waitDeadline())) {
                $transaction->closeUnpaid('timed_out');

                return $this->redirectToCheckout($transaction)->with('payment_failed', $this->failedNotice([], 'timed_out'));
            }

            return redirect()->route('payments.waiting', $transaction);
        }

        if ($outcome === 'failed' || ! $this->verificationMatches($transaction, $verified)) {
            $transaction->update(['status' => 'failed', 'meta' => $verified]);

            return $this->redirectToCheckout($transaction)->with('payment_failed', $this->failedNotice($verified));
        }

        $result = $this->finalize($transaction, $verified, $request);

        $redirect = redirect()->route($result['route'], $result['params']);

        return $result['status_message'] ? $redirect->with('status', $result['status_message']) : $redirect;
    }

    /**
     * Added 2026-09-28 once the real sandbox showed how v4 Mobile Money actually
     * behaves: the charge comes back `pending` with a "payment_instruction" (approve
     * on your phone) and no Flutterwave page to redirect to. This is where the client
     * waits meanwhile. The page re-checks by sending the browser to callback() every
     * few seconds (for the first 10 minutes after the charge was created), and
     * callback() either finalizes and moves them on, or bounces them back here.
     */
    public function waiting(Request $request, PaymentTransaction $transaction): View|RedirectResponse
    {
        abort_unless(
            $transaction->client_profile_id === $request->user()->clientProfile?->id,
            403
        );

        if ($transaction->processed_at) {
            return $this->redirectAfter($transaction);
        }

        if ($transaction->status !== 'pending') {
            return $this->redirectToCheckout($transaction)->with('payment_failed', $this->failedNotice($transaction->meta ?? [], $transaction->closedReason()));
        }

        return view('payments.waiting', [
            'deadline' => $transaction->waitDeadline(),
            'statusUrl' => route('payments.status', $transaction),
            'cancelUrl' => route('payments.cancel', $transaction),
            'transaction' => $transaction,
            'instruction' => $transaction->meta['instruction'] ?? null,
            'checkUrl' => route('payments.callback', ['tx_ref' => $transaction->tx_ref]),
            'autoCheck' => $transaction->created_at->gt(now()->subMinutes(10)),
            'canSimulate' => $this->simulationAllowed(),
        ]);
    }

    /**
     * The waiting page asks this quietly every few seconds (2026-11-04) instead of
     * reloading itself. Same rules as the app's status check, including the deadline.
     * `next` is where the browser should go once there's an answer.
     */
    public function status(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->client_profile_id === $request->user()->clientProfile?->id, 403);

        $check = $this->checkForApi($transaction, $request);

        return response()->json([
            'state' => $check['state'],
            'message' => $check['message'],
            'seconds_left' => max(0, (int) now()->diffInSeconds($transaction->waitDeadline(), false)),
            'next' => match ($check['state']) {
                'succeeded' => route('payments.callback', ['tx_ref' => $transaction->tx_ref]),
                'failed' => route('payments.waiting', $transaction),
                default => null,
            },
        ]);
    }

    /** "Cancel payment" on the waiting page (2026-11-04). */
    public function cancel(Request $request, PaymentTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->client_profile_id === $request->user()->clientProfile?->id, 403);

        if ($transaction->processed_at) {
            return $this->redirectAfter($transaction);
        }

        $transaction->closeUnpaid('client_cancelled');
        AuditLog::record($request, 'payment.cancelled_by_client', 'Client stopped waiting for a Mobile Money payment.', ['payment_transaction_id' => $transaction->id]);

        return $this->redirectToCheckout($transaction)->with('payment_failed', $this->failedNotice([], 'client_cancelled'));
    }

    /** The same for the app: POST api/v1/payments/{transaction}/cancel. */
    public function cancelForApi(PaymentTransaction $transaction, ?Request $request): array
    {
        if ($transaction->processed_at) {
            return ['state' => 'succeeded', 'message' => null];
        }

        $transaction->closeUnpaid('client_cancelled');
        AuditLog::record($request, 'payment.cancelled_by_client', 'Client stopped waiting for a Mobile Money payment (app).', ['payment_transaction_id' => $transaction->id]);

        return ['state' => 'failed', 'message' => $this->failedNotice([], 'client_cancelled')['text']];
    }

    /**
     * One background check of an unfinished payment (2026-11-04), for
     * mindwell:payments-reconcile. Returns what happened: completed, failed, closed,
     * waiting, mismatch or unreachable.
     */
    public function reconcile(PaymentTransaction $transaction): string
    {
        if ($transaction->processed_at || ! $transaction->flw_transaction_id) {
            return 'skipped';
        }

        try {
            $verified = $this->verify($transaction);
        } catch (Throwable $e) {
            return 'unreachable';
        }

        $outcome = $this->classifyStatus($verified);

        if ($outcome === 'succeeded') {
            if (! $this->verificationMatches($transaction, $verified)) {
                Log::warning('Reconcile: service says paid but amount/reference do not match', ['payment_transaction_id' => $transaction->id, 'verified' => $verified]);

                return 'mismatch';
            }
            $this->finalize($transaction, $verified, null);

            return 'completed';
        }

        if ($outcome === 'failed') {
            $transaction->update(['status' => 'failed', 'meta' => array_merge(['closed_reason' => $transaction->closedReason()], $verified)]);

            return 'failed';
        }

        if ($transaction->status === 'pending' && now()->gte($transaction->waitDeadline())) {
            $transaction->closeUnpaid('timed_out');

            return 'closed';
        }

        return 'waiting';
    }

    /**
     * LOCAL SANDBOX TESTING ONLY — added 2026-09-28 after confirming Flutterwave's v4
     * sandbox never completes a Mobile Money charge on its own (7 test charges were all
     * still "pending" 24+ minutes later, and every X-Scenario-Key tried was ignored).
     * Without this, nothing after payment (session → routing/pending-approval,
     * subscription credits) could be tested at all.
     *
     * Runs the exact same finalize() a real confirmation does — only the "did
     * Flutterwave say succeeded?" step is faked. 404s unless simulationAllowed(), so it
     * cannot exist in production or against live Flutterwave.
     */
    public function simulate(Request $request, PaymentTransaction $transaction): RedirectResponse
    {
        abort_unless($this->simulationAllowed(), 404);
        abort_unless(
            $transaction->client_profile_id === $request->user()->clientProfile?->id,
            403
        );

        if ($transaction->processed_at) {
            return $this->redirectAfter($transaction);
        }

        if ($transaction->status !== 'pending') {
            return $this->redirectToCheckout($transaction);
        }

        $outcome = $request->validate(['outcome' => ['required', 'in:approve,fail']])['outcome'];

        Log::warning('SIMULATED payment outcome (sandbox only)', [
            'tx_ref' => $transaction->tx_ref,
            'gateway' => $transaction->gateway,
            'outcome' => $outcome,
        ]);

        // Mirrors the verify() fields this controller reads, plus a `simulated` flag so
        // the stored meta makes it obvious afterwards.
        $simulated = $this->simulatedResult($transaction, $outcome);

        if ($outcome === 'fail') {
            $transaction->update(['status' => 'failed', 'meta' => $simulated]);

            return $this->redirectToCheckout($transaction)->with('payment_failed', $this->failedNotice($simulated));
        }

        $result = $this->finalize($transaction, $simulated, $request);

        $redirect = redirect()->route($result['route'], $result['params']);

        return $result['status_message'] ? $redirect->with('status', $result['status_message']) : $redirect;
    }

    /**
     * Both conditions, not either: running locally AND pointed at Flutterwave's
     * sandbox. A local machine accidentally configured with live credentials/base URL
     * still gets no simulate button.
     */
    public function simulationAllowed(): bool
    {
        // Local and the staging test site (go-live 2026-10-31: "Allow Test mode on staging").
        // Never production, and only while pointed at the Flutterwave sandbox.
        // MarzPay (2026-11-02): its sandbox is a setting on the business account, not a
        // separate address, so MARZPAY_SANDBOX=true stands in for the URL check.
        // 2026-11-03: off unless PAYMENT_TEST_MODE=true, so testing the real flow never
        // shows a way around paying.
        if (! config('payments.test_mode') || ! app()->environment('local', 'staging')) {
            return false;
        }

        return config('payments.gateway') === 'marzpay'
            ? (bool) config('marzpay.sandbox')
            : str_contains((string) config('flutterwave.base_url'), 'sandbox');
    }

    /** A made-up "the service said …" answer for the Test mode buttons. */
    private function simulatedResult(PaymentTransaction $transaction, string $outcome): array
    {
        return [
            'gateway' => $transaction->gateway,
            'id' => $transaction->flw_transaction_id,
            'status' => $outcome === 'approve' ? 'succeeded' : 'failed',
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'reference' => $this->expectedReference($transaction),
            'simulated' => true,
            'sandbox' => true,
        ];
    }

    /**
     * Asks the service that started this payment what happened to it. Both answers come
     * back with status / amount / currency / reference, so the rest of this class
     * doesn't care which service it was.
     */
    private function verify(PaymentTransaction $transaction, ?array $signedCallback = null): array
    {
        if ($transaction->gateway === 'marzpay') {
            $isSandbox = (bool) ($transaction->meta['sandbox'] ?? false);

            try {
                $result = app(MarzPayClient::class)->collection($transaction->flw_transaction_id);
            } catch (MarzPayException $e) {
                if ($e->errorCode !== 'NOT_FOUND') {
                    throw $e;
                }

                // MarzPay has no record of it. In sandbox that is normal (2026-11-03: the
                // sandbox never keeps test payments). A SIGNED sandbox callback on
                // local/staging is then the only word we'll get, so it is used as the
                // answer there. Never in production, never for a live payment.
                if ($signedCallback && $isSandbox && app()->environment('local', 'staging')
                    && ($fromCallback = MarzPayClient::normalize($signedCallback))) {
                    return array_merge($fromCallback, ['sandbox' => true, 'verified_by' => 'signed_sandbox_callback']);
                }

                // Otherwise: not an error the client should see, just "not confirmed yet".
                // (Live, a payment MarzPay can't find is never treated as paid.)
                Log::info('MarzPay has no record of this payment (yet)', ['payment_transaction_id' => $transaction->id, 'sandbox' => $isSandbox]);

                return [
                    'gateway' => 'marzpay',
                    'status' => 'pending',
                    'marzpay_status' => 'not_found',
                    'reference' => $transaction->gateway_reference,
                    'amount' => null,
                    'currency' => $transaction->currency,
                    'sandbox' => $isSandbox,
                ];
            }

            // The status answer may not say "sandbox"; the payment remembers it from the start.
            $result['sandbox'] = $result['sandbox'] || $isSandbox;

            return $result;
        }

        return app(FlutterwaveClient::class)->verifyCharge($transaction->flw_transaction_id);
    }

    /** The reference WE gave the service: MarzPay's UUID, or Flutterwave's tx_ref. */
    private function expectedReference(PaymentTransaction $transaction): string
    {
        return $transaction->gateway === 'marzpay'
            ? (string) $transaction->gateway_reference
            : (string) $transaction->tx_ref;
    }

    /**
     * Flutterwave's webhook — see the class docblock for why this exists but can't be
     * exercised locally yet. Always responds quickly (Flutterwave retries on anything
     * other than a 2xx), even for a reference this app doesn't recognize.
     */
    public function webhook(Request $request): JsonResponse
    {
        $expectedHash = config('flutterwave.webhook_secret_hash');

        // UNVERIFIED against v4's real webhook signing scheme — see the config file's
        // comment. Left in place (reusing v3's `verif-hash` convention) since this
        // route is unreachable without a public URL anyway; re-check before relying
        // on it in production.
        if (! $expectedHash || $request->header('verif-hash') !== $expectedHash) {
            abort(401);
        }

        $data = $request->input('data', []);
        // Field names here are best-effort — confirm against a real webhook delivery
        // once this route is actually reachable (needs a public URL first).
        $chargeId = $data['id'] ?? null;
        $reference = $data['reference'] ?? null;

        $transaction = $reference ? PaymentTransaction::where('tx_ref', $reference)->first() : null;

        if (! $transaction || $transaction->processed_at || ! $chargeId) {
            return response()->json(['status' => 'ignored']);
        }

        try {
            $verified = app(FlutterwaveClient::class)->verifyCharge((string) $chargeId);
        } catch (Throwable $e) {
            Log::warning('Flutterwave verify failed on webhook', ['tx_ref' => $reference, 'error' => $e->getMessage()]);

            // A non-2xx makes Flutterwave retry the webhook later, which is what we
            // want if OUR verify call failed transiently.
            return response()->json(['status' => 'error'], 202);
        }

        $outcome = $this->classifyStatus($verified);

        if ($outcome === 'succeeded' && $this->verificationMatches($transaction, $verified)) {
            $this->finalize($transaction, $verified, null);
        } elseif ($outcome === 'failed') {
            $transaction->update(['status' => 'failed', 'meta' => $verified]);
        }
        // 'pending' outcomes are left alone — a later webhook delivery, or the
        // client's own visit to callback(), can still pick it up once it resolves.

        return response()->json(['status' => 'ok']);
    }

    /**
     * MarzPay's callback (2026-11-02): POST /webhooks/marzpay, outside the login group
     * and exempt from CSRF (bootstrap/app.php).
     *
     *  - Signed: when MARZPAY_WEBHOOK_SECRET is set, a missing or wrong
     *    X-MarzPay-Signature is refused with 401.
     *  - Never trusted on its own: the body only tells us WHICH payment to look at. The
     *    outcome always comes from asking MarzPay again (verify()), like Flutterwave's.
     *  - Idempotent: MarzPay may send the same one twice; finalize()'s processed_at
     *    claim makes the second a no-op.
     *  - Takes both shapes: the direct callback_url body and the dashboard's {data: …}.
     */
    public function marzpayWebhook(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $secret = (string) config('marzpay.webhook_secret');
        $signed = $secret !== '';

        if ($signed && ! MarzPayClient::signatureValid($raw, $request->header('X-MarzPay-Signature'), $request->header('X-MarzPay-Timestamp'), $secret)) {
            Log::warning('MarzPay callback refused: bad or missing signature', [
                'has_signature' => $request->headers->has('X-MarzPay-Signature'),
                'ip' => $request->ip(),
            ]);
            if (config('marzpay.log_payloads')) {
                MarzPayClient::channel()->warning('Callback REFUSED (signature)', ['headers' => $this->marzHeaders($request), 'body' => $raw]);
            }

            return response()->json(['status' => 'unauthorized'], 401);
        }

        // For the admin Overview's payment service card ("last callback 4 min ago").
        \Illuminate\Support\Facades\Cache::forever('marzpay.last_callback_at', now()->toIso8601String());

        $body = json_decode($raw, true) ?: [];
        $tx = MarzPayClient::findTransaction($body);
        $reference = $tx['reference'] ?? null;
        $uuid = $tx['uuid'] ?? null;

        if (config('marzpay.log_payloads')) {
            MarzPayClient::channel()->info('Callback received', ['signed' => $signed, 'headers' => $this->marzHeaders($request), 'body' => $body]);
        }

        $transaction = null;
        if ($reference) {
            $transaction = PaymentTransaction::where('gateway', 'marzpay')->where('gateway_reference', $reference)->first();
        }
        if (! $transaction && $uuid) {
            $transaction = PaymentTransaction::where('gateway', 'marzpay')->where('flw_transaction_id', $uuid)->first();
        }

        if (! $transaction || $transaction->processed_at || ! $transaction->flw_transaction_id) {
            return response()->json(['status' => 'ignored']);
        }

        try {
            $verified = $this->verify($transaction, $signed ? $body : null);
        } catch (Throwable $e) {
            Log::warning('MarzPay re-check failed on callback', ['reference' => $reference, 'error' => $e->getMessage()]);

            // Not a 2xx, so MarzPay can try again; the client's status checks keep
            // going meanwhile.
            return response()->json(['status' => 'error'], 503);
        }

        $outcome = $this->classifyStatus($verified);

        if ($outcome === 'succeeded' && $this->verificationMatches($transaction, $verified)) {
            $this->finalize($transaction, $verified, null);
        } elseif ($outcome === 'failed') {
            $transaction->update(['status' => 'failed', 'meta' => $verified]);
        } elseif ($outcome === 'succeeded') {
            Log::warning('MarzPay says completed but amount/currency/reference do not match', [
                'payment_transaction_id' => $transaction->id,
                'verified' => $verified,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /** Only the headers worth seeing while testing; never Authorization or cookies. */
    private function marzHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->filter(fn ($v, $k) => str_starts_with($k, 'x-marzpay') || in_array($k, ['content-type', 'user-agent'], true))
            ->map(fn ($v) => implode(',', $v))
            ->all();
    }

    /**
     * A voucher covered the whole price (2026-10-07): there's nothing to charge, so the
     * Mobile Money step is skipped and the payment is completed straight away — same
     * finalize() path as a real payment (session/plan/renewal, receipt, redemption).
     */
    public function completeWithoutCharge(PaymentTransaction $transaction, Request $request): RedirectResponse
    {
        $result = $this->finalizeFree($transaction, $request);

        $redirect = redirect()->route($result['route'], $result['params']);

        return $redirect->with('status', $result['status_message'] ?? 'Your voucher covers the full price. There is nothing to pay.');
    }

    /** The voucher-covered case without a redirect — shared with the mobile API (2026-10-09). */
    public function finalizeFree(PaymentTransaction $transaction, ?Request $request): array
    {
        abort_unless((float) $transaction->amount <= 0 && $transaction->voucher_id, 422);

        return $this->finalize($transaction, [
            'status' => 'succeeded',
            'amount' => 0,
            'currency' => $transaction->currency,
            'reference' => $transaction->tx_ref,
            'voucher_free' => true,
        ], $request);
    }

    /**
     * One status check for the mobile API (2026-10-09) — the same steps callback() takes,
     * returned as data instead of a redirect:
     *   ['state' => 'succeeded'|'pending'|'failed'|'unreachable', 'message' => ?string]
     * 'succeeded' means finalize() has run (now or earlier).
     */
    public function checkForApi(PaymentTransaction $transaction, ?Request $request): array
    {
        if ($transaction->processed_at) {
            return ['state' => 'succeeded', 'message' => null];
        }
        if ($transaction->status !== 'pending') {
            return ['state' => 'failed', 'message' => $this->failedNotice($transaction->meta ?? [], $transaction->closedReason())['text']];
        }
        if (! $transaction->flw_transaction_id) {
            return ['state' => 'failed', 'message' => 'Payment could not be confirmed. Please try again.'];
        }

        try {
            $verified = $this->verify($transaction);
        } catch (Throwable $e) {
            Log::warning('Payment verify failed (API)', ['tx_ref' => $transaction->tx_ref, 'gateway' => $transaction->gateway, 'error' => $e->getMessage()]);

            // Not a failure of the payment itself — keep waiting, never ask to pay again.
            return ['state' => 'unreachable', 'message' => "We couldn't reach the payment service just now. We'll keep checking."];
        }

        $outcome = $this->classifyStatus($verified);

        if ($outcome === 'pending') {
            // 2026-11-04: MarzPay can keep a cancelled prompt "pending" for 10+ minutes
            // while it re-sends it, so we stop at our own deadline.
            if (now()->gte($transaction->waitDeadline())) {
                $transaction->closeUnpaid('timed_out');

                return ['state' => 'failed', 'message' => $this->failedNotice([], 'timed_out')['text']];
            }

            return ['state' => 'pending', 'message' => null];
        }

        if ($outcome === 'failed' || ! $this->verificationMatches($transaction, $verified)) {
            $transaction->update(['status' => 'failed', 'meta' => $verified]);

            return ['state' => 'failed', 'message' => $this->failedNotice($verified)['text']];
        }

        $result = $this->finalize($transaction, $verified, $request);

        return ['state' => 'succeeded', 'message' => $result['status_message']];
    }

    /** Local sandbox only, for the API — same rules as simulate(). */
    public function simulateForApi(PaymentTransaction $transaction, string $outcome, ?Request $request): array
    {
        abort_unless($this->simulationAllowed(), 404);

        if ($transaction->processed_at) {
            return ['state' => 'succeeded', 'message' => null];
        }
        if ($transaction->status !== 'pending') {
            return ['state' => 'failed', 'message' => 'Payment was not completed.'];
        }

        Log::warning('SIMULATED payment outcome via API (sandbox only)', ['tx_ref' => $transaction->tx_ref, 'gateway' => $transaction->gateway, 'outcome' => $outcome]);

        $simulated = $this->simulatedResult($transaction, $outcome);

        if ($outcome !== 'approve') {
            $transaction->update(['status' => 'failed', 'meta' => $simulated]);

            return ['state' => 'failed', 'message' => $this->failedNotice($simulated)['text']];
        }

        $result = $this->finalize($transaction, $simulated, $request);

        return ['state' => 'succeeded', 'message' => $result['status_message']];
    }

    /**
     * What to tell a client whose payment didn't go through (2026-11-03, after the first
     * live MarzPay test with a cancelled prompt). Plain words, the likely reason when the
     * service gives one, and always that no money was taken.
     *
     * @return array{kind: string, reason: string, text: string}
     */
    private function failedNotice(array $verified, ?string $kind = null): array
    {
        $kind = $kind ?: MarzPayClient::failureKind($verified);

        // 2026-11-04: we stopped waiting (the client cancelled, or no answer in time). The
        // phone may still ask again, so tell them to decline it.
        if (in_array($kind, ['client_cancelled', 'timed_out'], true)) {
            $reason = $kind === 'client_cancelled'
                ? 'You cancelled this payment.'
                : 'We didn\'t get a confirmation from your phone in time.';
            $after = 'No money has been taken. If your phone asks you to approve this payment again, decline it. If you do approve it, we\'ll still record it and let you know.';

            return ['kind' => $kind, 'reason' => $reason, 'after' => $after, 'text' => $reason.' '.$after];
        }

        $reason = [
            'insufficient' => 'There wasn\'t enough money on that Mobile Money account.',
            'declined' => 'The payment was cancelled on the phone, or the PIN wasn\'t accepted.',
            'timeout' => 'The request on your phone timed out before it was approved.',
            'number' => 'That number couldn\'t receive a Mobile Money request.',
            'mismatch' => 'We couldn\'t confirm this payment, so it wasn\'t used.',
            'unknown' => 'The payment wasn\'t approved on your phone.',
        ][$kind];

        return [
            'kind' => $kind,
            'reason' => $reason,
            'text' => $reason.' You have not been charged. You can try again.',
        ];
    }

    /**
     * Best-effort normalization of whatever status string Flutterwave's v4 API
     * returns — 'succeeded' and 'successful' are both accepted as success since the
     * exact spelling wasn't confirmed with full certainty against the real sandbox
     * during this round (Flutterwave's own articles used both). Anything not
     * recognized as success or a definite failure is treated as still pending, rather
     * than guessed at either way.
     */
    private function classifyStatus(array $verified): string
    {
        $status = strtolower((string) ($verified['status'] ?? ''));

        return match (true) {
            in_array($status, ['succeeded', 'successful'], true) => 'succeeded',
            in_array($status, ['failed', 'declined', 'cancelled', 'canceled'], true) => 'failed',
            default => 'pending',
        };
    }

    /**
     * Confirms Flutterwave's verify response actually matches what THIS transaction
     * expected — a mismatched amount/currency/reference is treated as unverified even
     * if Flutterwave's own status says success.
     */
    private function verificationMatches(PaymentTransaction $transaction, array $verified): bool
    {
        $reference = $verified['reference'] ?? null;
        $currency = $verified['currency'] ?? null;
        $amount = $verified['amount'] ?? null;

        return $reference === $this->expectedReference($transaction)
            && $currency === $transaction->currency
            && $amount !== null
            && abs((float) $amount - (float) $transaction->amount) < 0.01;
    }

    /**
     * The one place a PaymentTransaction actually becomes a paid session or a real
     * subscription — guarded by `processed_at` so callback() and webhook() can't both
     * apply the same successful charge twice.
     *
     * @return array{route: string, params: array, status_message: ?string}
     */
    public function finalize(PaymentTransaction $transaction, array $verified, ?Request $request): array
    {
        // Claim the transaction atomically (2026-10-09): with the mobile app polling as well
        // as the browser callback and the webhook, two checks can land at the same moment.
        // Only the one that flips processed_at from NULL goes on to apply the payment.
        // The claim and applying the payment happen in ONE database transaction: if applying
        // fails, the claim is rolled back too, so a later check can try again instead of
        // reporting a paid-for-nothing success.
        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $verified, $request) {
            $claimed = PaymentTransaction::whereKey($transaction->getKey())
                ->whereNull('processed_at')
                ->update(['status' => 'successful', 'processed_at' => now()]);

            if (! $claimed) {
                return null;
            }

            $transaction->refresh();
            $wasClosed = $transaction->closedReason();
            $transaction->update(['meta' => array_filter(array_merge($verified, ['closed_reason' => $wasClosed]), fn ($v) => $v !== null)]);

            // 2026-11-04: a payment confirmed AFTER the client stopped waiting (a re-sent
            // prompt approved later) may find its session or plan already paid by a newer
            // attempt. The money is real, so it's kept as received but not applied twice:
            // admins are told to refund or credit it.
            if ($dup = $this->alreadyPaidBy($transaction)) {
                $transaction->update(['meta' => array_merge($transaction->meta ?? [], ['unapplied' => 'already_paid', 'duplicate_of' => $dup])]);

                return ['route' => $transaction->purpose === 'session' ? 'sessions.history' : 'subscription.index', 'params' => [], 'status_message' => null, 'unapplied' => true];
            }

            if ($transaction->purpose === 'session') {
                return $this->finalizeSession($transaction, $request);
            }

            // Renewal of an existing plan (2026-10-01) vs. buying / switching to a plan.
            return $transaction->isRenewal()
                ? app(SubscriptionController::class)->finalizeRenewal($transaction, $request)
                : app(SubscriptionController::class)->finalizeSubscription($transaction, $request);
        });

        if ($result === null) {
            $transaction->refresh();

            return $transaction->purpose === 'session'
                ? ['route' => 'sessions.history', 'params' => [], 'status_message' => null]
                : ['route' => 'subscription.index', 'params' => [], 'status_message' => null];
        }

        $transaction->refresh();

        // Receipt number + receipt email (2026-10-02). Never throws.
        // Vouchers (2026-10-07): a use only counts once the payment has gone through.
        app(Vouchers::class)->recordRedemption($transaction);

        app(Receipts::class)->issue($transaction);

        if (! empty($result['unapplied'])) {
            app(\App\Services\Notifier::class)->paymentNeedsAttention($transaction, \App\Models\User::where('role', 'admin')->get());
            AuditLog::record($request, 'payment.unapplied', 'A late Mobile Money payment arrived for something already paid. It needs a refund or credit.', ['payment_transaction_id' => $transaction->id]);
            $result['status_message'] = 'We received your payment of '.$transaction->currency.' '.number_format((float) $transaction->amount).', but this was already paid. Our team will refund or credit it and let you know.';
        }

        return $result;
    }

    /** Another successful payment already covers what this one was for? Its id, or null. */
    private function alreadyPaidBy(PaymentTransaction $transaction): ?string
    {
        if ($transaction->purpose === 'session') {
            $session = $transaction->therapySession;
            if (! $session || $session->status === 'pending_payment') {
                return null;
            }

            // The session moved on without this payment (paid by a newer attempt, or cancelled).
            return PaymentTransaction::where('therapy_session_id', $session->id)->where('id', '!=', $transaction->id)
                ->where('status', 'successful')->value('id') ?? 'session-no-longer-waiting';
        }

        if (! $transaction->closedReason()) {
            return null; // plans: only late (closed) payments are checked
        }

        return PaymentTransaction::where('client_profile_id', $transaction->client_profile_id)
            ->where('purpose', 'subscription')->where('id', '!=', $transaction->id)
            ->where('subscription_plan_id', $transaction->subscription_plan_id)
            ->where('client_subscription_id', $transaction->client_subscription_id)
            ->where('status', 'successful')
            ->where('processed_at', '>=', $transaction->created_at->copy()->subMinutes(5))
            ->value('id');
    }

    private function finalizeSession(PaymentTransaction $transaction, ?Request $request): array
    {
        $session = $transaction->therapySession;

        if (! $session || $session->status !== 'pending_payment') {
            return ['route' => 'sessions.history', 'params' => [], 'status_message' => null];
        }

        $isSelfBooked = in_array($session->routing_method, ['self_booked', 'therapist_scheduled'], true);

        $session->update([
            'status' => $isSelfBooked ? 'pending_approval' : 'routing',
            'payment_method' => 'one_off',
            'amount_charged' => $transaction->amount,
            'entered_lobby_at' => $isSelfBooked ? null : now(),
        ]);

        AuditLog::record($request, 'session.paid', 'Client paid for a session via Mobile Money ('.($transaction->gateway === 'marzpay' ? 'MarzPay' : 'Flutterwave').').', [
            'therapy_session_id' => $session->id,
            'payment_transaction_id' => $transaction->id,
        ]);

        return [
            'route' => $isSelfBooked ? 'sessions.pending-approval' : 'sessions.lobby',
            'params' => [$session],
            'status_message' => null,
        ];
    }

    private function redirectToCheckout(PaymentTransaction $transaction): RedirectResponse
    {
        if ($transaction->purpose === 'session') {
            return redirect()->route('sessions.checkout', $transaction->therapy_session_id);
        }

        return $transaction->isRenewal()
            ? redirect()->route('subscription.renew')
            : redirect()->route('subscription.checkout', $transaction->subscription_plan_id);
    }

    private function redirectAfter(PaymentTransaction $transaction): RedirectResponse
    {
        if ($transaction->purpose === 'subscription') {
            return redirect()->route('subscription.index');
        }

        $session = $transaction->therapySession;

        return $session
            ? redirect()->route($this->sessionRouteFor($session), $session)
            : redirect()->route('sessions.history');
    }

    /**
     * Duplicated from SessionRequestController::routeForStatus() on purpose — see that
     * method's usage elsewhere in this codebase for why small duplication like this is
     * preferred over a shared helper for two callers.
     */
    private function sessionRouteFor(TherapySession $session): string
    {
        return match ($session->status) {
            'pending_payment' => 'sessions.checkout',
            'routing' => 'sessions.lobby',
            'pending_approval' => 'sessions.pending-approval',
            'scheduled' => 'sessions.connected',
            default => 'sessions.history',
        };
    }
}
