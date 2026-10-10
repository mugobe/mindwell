<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FlutterwavePaymentController;
use App\Models\PaymentTransaction;
use App\Models\TherapySession;
use App\Models\Voucher;
use App\Services\Checkout;
use App\Services\Vouchers;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile API v1 — paying for one session, and following any payment until it's done
 * (2026-10-09). Uses App\Services\Checkout (shared with the website) to start it and
 * FlutterwavePaymentController's own verify/finalize to finish it, so a payment from the
 * app is treated exactly like one from the website (receipt, voucher use, audit log).
 *
 * Flow in the app: GET quote → POST pay → poll GET payments/{id} every ~4s while the
 * client approves on their phone → succeeded / failed.
 */
class CheckoutController extends Controller
{
    private function ownSession(Request $request, TherapySession $s): void
    {
        abort_unless($s->client_profile_id === $request->user()->clientProfile?->id, 403);
    }

    private function ownTx(Request $request, PaymentTransaction $t): void
    {
        abort_unless($t->client_profile_id === $request->user()->clientProfile?->id, 403);
    }

    /** Price for a session, with an optional ?voucher=CODE preview. */
    public function quote(Request $request, TherapySession $therapySession, Vouchers $vouchers): JsonResponse
    {
        $this->ownSession($request, $therapySession);

        if ($therapySession->status !== 'pending_payment') {
            return response()->json(['message' => 'This session is already paid for or covered.', 'session' => ApiPresenter::session($therapySession)], 409);
        }

        $q = $vouchers->quote($request->query('voucher'), Voucher::FOR_SESSION, $therapySession->listPrice(), $therapySession->clientProfile,
            ['therapy_session_id' => $therapySession->id]);

        return response()->json(['quote' => ApiPresenter::quote($q), 'session' => ApiPresenter::session($therapySession->load('therapistProfile.user', 'venue'))]);
    }

    /** Body: voucher (optional), network (MTN|AIRTEL), phone_number — not needed if a voucher makes it free. */
    public function pay(Request $request, TherapySession $therapySession, Checkout $checkout, Vouchers $vouchers): JsonResponse
    {
        $this->ownSession($request, $therapySession);

        if ($therapySession->status !== 'pending_payment') {
            return response()->json(['message' => 'This session is already paid for or covered.', 'session' => ApiPresenter::session($therapySession)], 409);
        }

        // A retry after a dropped connection must not send a second PIN prompt.
        if ($recent = $this->recentPending(['purpose' => 'session', 'therapy_session_id' => $therapySession->id])) {
            return $this->alreadySent($recent);
        }

        $code = $request->input('voucher');
        $price = $therapySession->listPrice();
        $profile = $therapySession->clientProfile;
        $target = ['therapy_session_id' => $therapySession->id];

        $preview = $vouchers->quote($code, Voucher::FOR_SESSION, $price, $profile, $target);
        if (filled($code) && ! $preview['ok']) {
            return response()->json(['message' => $preview['error'], 'errors' => ['voucher' => [$preview['error']]]], 422);
        }
        $free = $preview['applied'] && $preview['total'] <= 0;
        $momo = $free ? null : $request->validate(Checkout::MOMO_RULES, Checkout::MOMO_MESSAGES);

        $result = $checkout->createTransaction($code, Voucher::FOR_SESSION, $price, $profile, $target, [
            'client_profile_id' => $therapySession->client_profile_id,
            'purpose' => 'session',
            'therapy_session_id' => $therapySession->id,
        ], 'MW-SESSION-', $momo);

        return $this->afterCreate($request, $result, $momo, $checkout);
    }

    /** Shared with PlanController: free → finalize now; otherwise start Mobile Money. */
    public function afterCreate(Request $request, array $result, ?array $momo, Checkout $checkout): JsonResponse
    {
        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        $transaction = $result['transaction'];

        if ($result['free']) {
            $done = app(FlutterwavePaymentController::class)->finalizeFree($transaction, $request);

            return response()->json($this->state($transaction->fresh(), 'succeeded', $done['status_message'] ?? 'Your voucher covered the full price — nothing to pay.'), 201);
        }

        $charge = $checkout->startMobileMoney($transaction, $momo, $request->user());
        if (! $charge['ok']) {
            return response()->json(['message' => $charge['error'], 'payment' => ApiPresenter::transaction($transaction->fresh())], 502);
        }

        return response()->json($this->state($transaction->fresh(), 'pending',
            $charge['instruction'] ?? 'Check your phone and enter your Mobile Money PIN to approve the payment.'), 201);
    }

    /**
     * A Mobile Money request for the same thing started in the last 3 minutes and still
     * waiting — so the app's retry (weak connection, double tap) reuses it instead of
     * charging twice.
     */
    public function recentPending(array $where): ?PaymentTransaction
    {
        return PaymentTransaction::where($where)
            ->where('status', 'pending')
            ->whereNull('processed_at')
            ->whereNotNull('flw_transaction_id')
            ->where('created_at', '>=', now()->subMinutes(3))
            ->latest()
            ->first();
    }

    public function alreadySent(PaymentTransaction $t): JsonResponse
    {
        return response()->json($this->state($t, 'pending',
            ($t->meta['instruction'] ?? null) ?: "We've already sent a payment request to your phone — approve it there."), 200);
    }

    /** Poll this while the client approves on their phone. */
    public function status(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        $this->ownTx($request, $transaction);

        $check = app(FlutterwavePaymentController::class)->checkForApi($transaction, $request);

        return response()->json($this->state($transaction->fresh(), $check['state'], $check['message']));
    }

    /** LOCAL SANDBOX ONLY (404 elsewhere): approve or fail a pending Mobile Money payment. */
    public function simulate(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        $this->ownTx($request, $transaction);
        $outcome = $request->validate(['outcome' => ['required', 'in:approve,fail']])['outcome'];

        $check = app(FlutterwavePaymentController::class)->simulateForApi($transaction, $outcome, $request);

        return response()->json($this->state($transaction->fresh(), $check['state'], $check['message']));
    }

    private function state(PaymentTransaction $t, string $state, ?string $message): array
    {
        $session = $t->purpose === 'session' ? $t->therapySession()->with('therapistProfile.user', 'venue')->first() : null;

        return [
            'state' => $state,                     // pending | succeeded | failed | unreachable
            'message' => $message,
            'payment' => ApiPresenter::transaction($t),
            'session' => $session ? ApiPresenter::session($session) : null,
            'can_simulate' => $state === 'pending' && app(FlutterwavePaymentController::class)->simulationAllowed(),
        ];
    }
}
