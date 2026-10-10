<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Voucher;
use App\Models\TherapySession;
use App\Services\Checkout;
use App\Services\SessionRouter;
use App\Services\Vouchers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The client-facing half of the "pay/use a credit → get routed → warm lobby" flow
 * agreed with Dr. Ntabade on 2026-09-25. See claude/mindwell-client-feedback.md for
 * the full discussion.
 *
 * Extended 2026-09-26 (third round of client testing feedback): a self-booked or
 * therapist-scheduled session no longer jumps straight to 'scheduled' — it passes
 * through a 'pending_approval' step first so admin/routing can check it before it's
 * confirmed. See pendingApproval() below and Admin\SessionRoutingController.
 *
 * Extended 2026-09-27: pay() used to mark a session paid directly (no real gateway
 * existed yet). It now hands off to real Flutterwave — see the comment on pay()
 * below and App\Http\Controllers\FlutterwavePaymentController, which is where a
 * session actually GETS marked paid once Flutterwave confirms the charge.
 */
class SessionRequestController extends Controller
{
    private function authorizeOwnSession(Request $request, TherapySession $therapySession): void
    {
        abort_unless(
            $therapySession->client_profile_id === $request->user()->clientProfile?->id,
            403
        );
    }

    /**
     * Where a client landing on the "wrong" page for this session's current status
     * should actually be sent. Centralized so checkout/pay/lobby/connected all agree
     * with pendingApproval() about what each status means.
     */
    private function routeForStatus(TherapySession $therapySession): string
    {
        return match ($therapySession->status) {
            'pending_payment' => 'sessions.checkout',
            'routing' => 'sessions.lobby',
            'pending_approval' => 'sessions.pending-approval',
            'scheduled' => 'sessions.connected',
            default => 'sessions.history',
        };
    }

    public function checkout(Request $request, TherapySession $therapySession): View|RedirectResponse
    {
        $this->authorizeOwnSession($request, $therapySession);

        if ($therapySession->status !== 'pending_payment') {
            return redirect()->route($this->routeForStatus($therapySession), $therapySession);
        }

        return view('sessions.checkout', [
            'session' => $therapySession->load('therapistProfile.user'),
            'price' => $therapySession->listPrice(),
            // Vouchers (2026-10-07): ?voucher=CODE previews the discount before paying.
            'quote' => app(Vouchers::class)->quote(
                $request->query('voucher'), Voucher::FOR_SESSION, $therapySession->listPrice(), $therapySession->clientProfile,
                ['therapy_session_id' => $therapySession->id]),
        ]);
    }

    /**
     * Real Flutterwave integration, REBUILT 2026-09-27 for v4 (Mobile Money only — see
     * FlutterwaveClient's class docblock for why): this used to mark the session paid
     * directly. It now creates a PaymentTransaction, asks Flutterwave to initiate a
     * Mobile Money charge for it, and sends the client's browser on to approve it
     * (an MTN/Airtel PIN prompt) — nothing about THIS session actually changes yet.
     * The client lands back on FlutterwavePaymentController::callback() once that's
     * done (or Flutterwave gives no redirect_url at all, in which case this sends them
     * there directly), and that's where the session finally gets marked paid — see
     * FlutterwavePaymentController::finalizeSession().
     */
    public function pay(Request $request, TherapySession $therapySession): RedirectResponse
    {
        $this->authorizeOwnSession($request, $therapySession);

        if ($therapySession->status !== 'pending_payment') {
            return redirect()->route($this->routeForStatus($therapySession), $therapySession);
        }

        // Vouchers (2026-10-07): re-checked here, never trusted from the preview — and
        // again under a lock on the voucher when the payment is created. Since
        // 2026-10-09 the steps live in App\Services\Checkout, shared with the mobile API.
        $checkout = app(Checkout::class);
        $code = $request->input('voucher');
        $price = $therapySession->listPrice();
        $profile = $therapySession->clientProfile;
        $target = ['therapy_session_id' => $therapySession->id];

        $preview = app(Vouchers::class)->quote($code, Voucher::FOR_SESSION, $price, $profile, $target);
        if ($request->filled('voucher') && ! $preview['ok']) {
            return redirect()->route('sessions.checkout', $therapySession)->with('error', $preview['error']);
        }

        // Fully covered by a voucher: nothing to charge, so no Mobile Money step.
        $free = $preview['applied'] && $preview['total'] <= 0;
        $momo = $free ? null : $request->validate(Checkout::MOMO_RULES, Checkout::MOMO_MESSAGES);

        $result = $checkout->createTransaction($code, Voucher::FOR_SESSION, $price, $profile, $target, [
            'client_profile_id' => $therapySession->client_profile_id,
            'purpose' => 'session',
            'therapy_session_id' => $therapySession->id,
        ], 'MW-SESSION-', $momo);

        if (isset($result['error'])) {
            return redirect()->route('sessions.checkout', $therapySession)->with('error', $result['error']);
        }

        $transaction = $result['transaction'];

        if ($result['free']) {
            return app(FlutterwavePaymentController::class)->completeWithoutCharge($transaction, $request);
        }

        $charge = $checkout->startMobileMoney($transaction, $momo, $request->user());
        if (! $charge['ok']) {
            return redirect()->route('sessions.checkout', $therapySession)->with('error', $charge['error']);
        }

        if ($charge['redirect_url']) {
            return redirect()->away($charge['redirect_url']);
        }

        // The normal Mobile Money case (confirmed against the real sandbox 2026-09-28):
        // no Flutterwave page at all — the client approves on their phone while this
        // app waits. See FlutterwavePaymentController::waiting().
        return redirect()->route('payments.flutterwave.waiting', $transaction);
    }

    public function lobby(Request $request, TherapySession $therapySession): View|RedirectResponse
    {
        $this->authorizeOwnSession($request, $therapySession);

        if ($therapySession->status === 'pending_payment') {
            return redirect()->route('sessions.checkout', $therapySession);
        }

        if ($therapySession->status === 'pending_approval') {
            return redirect()->route('sessions.pending-approval', $therapySession);
        }

        if ($therapySession->status === 'scheduled') {
            return redirect()->route('sessions.connected', $therapySession);
        }

        // First time landing here, make one routing attempt immediately so a lucky client
        // doesn't sit and watch a spinner for no reason if a therapist happens to already
        // be free right now. If it fails, they wait in the lobby and this gets retried by
        // whatever polls sessions.status, or picked up manually by staff.
        if ($therapySession->status === 'routing' && ! $therapySession->routed_at) {
            app(SessionRouter::class)->attempt($therapySession);
            $therapySession->refresh();

            if ($therapySession->status === 'scheduled') {
                return redirect()->route('sessions.connected', $therapySession);
            }
        }

        return view('sessions.lobby', [
            'session' => $therapySession,
            'targetMinutes' => config('mindwell.lobby_target_minutes'),
        ]);
    }

    /**
     * Polled by the lobby view every few seconds so the page can move on the moment
     * staff (or a retried algorithmic attempt) places the client, without a manual refresh.
     */
    public function status(Request $request, TherapySession $therapySession): JsonResponse
    {
        $this->authorizeOwnSession($request, $therapySession);

        if ($therapySession->status === 'routing') {
            app(SessionRouter::class)->attempt($therapySession);
            $therapySession->refresh();
        }

        return response()->json([
            'status' => $therapySession->status,
            'redirect' => $therapySession->status === 'scheduled' ? route('sessions.connected', $therapySession) : null,
        ]);
    }

    /**
     * The bail-out the lobby offers once the target wait time has passed: give up
     * waiting to be routed and go pick a therapist/slot directly instead. Cancels the
     * waiting session so it stops sitting in the admin routing queue and can't later
     * get matched out from under someone who already booked themselves elsewhere.
     */
    public function cancel(Request $request, TherapySession $therapySession): RedirectResponse
    {
        $this->authorizeOwnSession($request, $therapySession);

        if ($therapySession->status === 'routing' || $therapySession->status === 'pending_payment') {
            $therapySession->update(['status' => 'cancelled']);
        }
        // If it's already 'scheduled' by the time this lands (staff or the algorithm
        // got there first), leave it alone — better a client ends up matched than
        // cancelled out from under them by a stray click.

        return redirect()->route('therapists.browse')
            ->with('status', 'No problem — pick a therapist and time that works for you below.');
    }

    /**
     * New 2026-09-26: the holding page for a direct booking or therapist-scheduled
     * follow-up while it waits on admin/routing's approve/decline decision (see
     * Admin\SessionRoutingController). No live polling here — unlike the lobby, there's
     * no notification system yet to make that meaningful, so the client just checks
     * back via "My sessions."
     */
    public function pendingApproval(Request $request, TherapySession $therapySession): View|RedirectResponse
    {
        $this->authorizeOwnSession($request, $therapySession);

        if ($therapySession->status !== 'pending_approval') {
            return redirect()->route($this->routeForStatus($therapySession), $therapySession);
        }

        return view('sessions.pending-approval', [
            'session' => $therapySession->load('therapistProfile.user'),
        ]);
    }

    public function connected(Request $request, TherapySession $therapySession): View|RedirectResponse
    {
        $this->authorizeOwnSession($request, $therapySession);

        if ($therapySession->status !== 'scheduled') {
            return redirect()->route($this->routeForStatus($therapySession), $therapySession);
        }

        return view('sessions.connected', [
            'session' => $therapySession->load('therapistProfile.user'),
        ]);
    }
}
