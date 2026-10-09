<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\TherapySession;
use App\Services\FlutterwaveClient;
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
            $verified = app(FlutterwaveClient::class)->verifyCharge($transaction->flw_transaction_id);
        } catch (Throwable $e) {
            Log::warning('Flutterwave verify failed on callback', ['tx_ref' => $txRef, 'error' => $e->getMessage()]);

            // Back to the waiting page, not checkout — the charge itself may be fine;
            // it's only OUR check that failed, and a new payment from checkout could
            // end up charging the client twice.
            return redirect()->route('payments.flutterwave.waiting', $transaction)
                ->with('error', "We couldn't reach Flutterwave to check this payment just now. We'll keep trying.");
        }

        // Kept until the first real test confirms the field names verifyCharge()
        // assumes are correct — Flutterwave's own v4 docs don't publish a full worked
        // example of this response.
        Log::info('Flutterwave verify response (callback)', ['tx_ref' => $txRef, 'verified' => $verified]);

        $outcome = $this->classifyStatus($verified);

        if ($outcome === 'pending') {
            // Not a failure — the client just hasn't approved it on their phone yet (or
            // Mobile Money is still processing). Leave the transaction as-is so the
            // waiting page's next check, or an eventual webhook, can pick it up.
            return redirect()->route('payments.flutterwave.waiting', $transaction);
        }

        if ($outcome === 'failed' || ! $this->verificationMatches($transaction, $verified)) {
            $transaction->update(['status' => 'failed', 'meta' => $verified]);

            return $this->redirectToCheckout($transaction)
                ->with('error', 'Payment was not completed. You can try again below.');
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
            return $this->redirectToCheckout($transaction)
                ->with('error', 'Payment was not completed. You can try again below.');
        }

        return view('payments.waiting', [
            'transaction' => $transaction,
            'instruction' => $transaction->meta['instruction'] ?? null,
            'checkUrl' => route('payments.flutterwave.callback', ['tx_ref' => $transaction->tx_ref]),
            'autoCheck' => $transaction->created_at->gt(now()->subMinutes(10)),
            'canSimulate' => $this->simulationAllowed(),
        ]);
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

        Log::warning('SIMULATED Flutterwave outcome (local sandbox only)', [
            'tx_ref' => $transaction->tx_ref,
            'outcome' => $outcome,
        ]);

        // Mirrors the real GET /charges/{id} response fields this controller reads,
        // plus a `simulated` flag so the stored meta makes it obvious afterwards.
        $simulated = [
            'id' => $transaction->flw_transaction_id,
            'status' => $outcome === 'approve' ? 'succeeded' : 'failed',
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'reference' => $transaction->tx_ref,
            'simulated' => true,
        ];

        if ($outcome === 'fail') {
            $transaction->update(['status' => 'failed', 'meta' => $simulated]);

            return $this->redirectToCheckout($transaction)
                ->with('error', 'Payment was not completed (simulated failure). You can try again below.');
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
    private function simulationAllowed(): bool
    {
        return app()->environment('local')
            && str_contains((string) config('flutterwave.base_url'), 'sandbox');
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
     * A voucher covered the whole price (2026-10-07): there's nothing to charge, so the
     * Mobile Money step is skipped and the payment is completed straight away — same
     * finalize() path as a real payment (session/plan/renewal, receipt, redemption).
     */
    public function completeWithoutCharge(PaymentTransaction $transaction, Request $request): RedirectResponse
    {
        abort_unless((float) $transaction->amount <= 0 && $transaction->voucher_id, 422);

        $result = $this->finalize($transaction, [
            'status' => 'succeeded',
            'amount' => 0,
            'currency' => $transaction->currency,
            'reference' => $transaction->tx_ref,
            'voucher_free' => true,
        ], $request);

        $redirect = redirect()->route($result['route'], $result['params']);

        return $redirect->with('status', $result['status_message'] ?? 'Your voucher covered the full price — nothing to pay.');
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

        return $reference === $transaction->tx_ref
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
    private function finalize(PaymentTransaction $transaction, array $verified, ?Request $request): array
    {
        $transaction->update([
            'status' => 'successful',
            'meta' => $verified,
            'processed_at' => now(),
        ]);

        if ($transaction->purpose === 'session') {
            $result = $this->finalizeSession($transaction, $request);
        } else {
            // Renewal of an existing plan (2026-10-01) vs. buying / switching to a plan.
            $result = $transaction->isRenewal()
                ? app(SubscriptionController::class)->finalizeRenewal($transaction, $request)
                : app(SubscriptionController::class)->finalizeSubscription($transaction, $request);
        }

        // Receipt number + receipt email (2026-10-02). Never throws.
        // Vouchers (2026-10-07): a use only counts once the payment has gone through.
        app(Vouchers::class)->recordRedemption($transaction);

        app(Receipts::class)->issue($transaction);

        return $result;
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

        AuditLog::record($request, 'session.paid', 'Client paid for a session via Flutterwave Mobile Money.', [
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
