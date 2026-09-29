<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientSubscription;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Services\FlutterwaveClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Client-facing self-checkout for subscription plans, added 2026-09-27. Before this,
 * the ONLY way a client got a ClientSubscription was an admin manually granting one
 * from Admin\ClientSubscriptionController — this is the self-serve version of that
 * same underlying record, reached from the client's own "My plan" page. Deliberately
 * NOT surfaced during onboarding/first booking (see the "gentler" placement discussion
 * in mindwell-betterhelp-proposal.md) — a client only sees this if they go looking
 * for it in the top nav.
 *
 * Payment: subscribe() used to mark a subscription active directly (placeholder, no
 * real gateway). As of the same day's real Flutterwave integration, it instead creates
 * a PaymentTransaction and sends the client to Flutterwave's hosted checkout — the
 * actual ClientSubscription only gets created by finalizeSubscription() below, called
 * from App\Http\Controllers\FlutterwavePaymentController once Flutterwave confirms
 * the charge.
 *
 * Re-subscribe rule (decided 2026-09-27): a client can only ever have ONE active
 * subscription. Buying a different plan while one is active REPLACES it immediately
 * — it does not stack. Since there's no real invoice to prorate against, "proration"
 * here means converting the value of unused credits (price / sessions_included on the
 * OLD plan) into an equivalent number of bonus credits on the NEW plan, so nothing is
 * silently lost even though no real money moves. See prorationFor() below.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->clientProfile;

        $activeSubscriptions = $profile
            ? ClientSubscription::where('client_profile_id', $profile->id)
                ->where('status', 'active')
                ->with('subscriptionPlan')
                ->get()
            : collect();

        $plans = SubscriptionPlan::orderBy('price')->get();

        return view('subscriptions.index', [
            // Only ever expect one, but see the class docblock on legacy admin-granted
            // duplicates — pick the most recently started one to display as "current."
            'activeSubscription' => $activeSubscriptions->sortByDesc('current_period_start')->first(),
            'totalCreditsRemaining' => $activeSubscriptions->sum('sessions_remaining'),
            'plans' => $plans,
        ]);
    }

    public function checkout(Request $request, SubscriptionPlan $plan): View
    {
        $profile = $request->user()->clientProfile;

        $activeSubscriptions = $profile
            ? ClientSubscription::where('client_profile_id', $profile->id)
                ->where('status', 'active')
                ->with('subscriptionPlan')
                ->get()
            : collect();

        return view('subscriptions.checkout', [
            'plan' => $plan,
            'activeSubscription' => $activeSubscriptions->sortByDesc('current_period_start')->first(),
            'proration' => $activeSubscriptions->isNotEmpty() ? $this->prorationFor($activeSubscriptions, $plan) : null,
        ]);
    }

    /**
     * Real Flutterwave integration, REBUILT 2026-09-27 for v4 (Mobile Money only — see
     * FlutterwaveClient's class docblock for why): creates a PaymentTransaction for
     * $plan->price and asks Flutterwave to initiate a Mobile Money charge for it, then
     * sends the client's browser on to approve it. Nothing about the client's
     * subscription changes yet — see finalizeSubscription() below, called only once
     * Flutterwave has actually confirmed the charge.
     */
    public function subscribe(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);

        $validated = $request->validate([
            'network' => ['required', 'in:MTN,AIRTEL'],
            'phone_number' => ['required', 'regex:/^(0|256)?7[0-9]{8}$/'],
        ], [
            'phone_number.regex' => 'Enter a valid Ugandan mobile number, e.g. 0772123456.',
        ]);

        $currency = config('flutterwave.currency');

        $transaction = PaymentTransaction::create([
            'client_profile_id' => $profile->id,
            'purpose' => 'subscription',
            'subscription_plan_id' => $plan->id,
            'tx_ref' => 'MW-SUB-'.strtoupper(Str::random(10)),
            'status' => 'pending',
            'amount' => $plan->price,
            'currency' => $currency,
        ]);

        try {
            $charge = app(FlutterwaveClient::class)->initiateMobileMoneyCharge(
                reference: $transaction->tx_ref,
                amount: (float) $plan->price,
                currency: $currency,
                network: $validated['network'],
                phoneNumber: $this->normalizePhoneNumber($validated['phone_number']),
                customer: [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
            );
        } catch (Throwable $e) {
            $transaction->update(['status' => 'failed']);

            return redirect()->route('subscription.checkout', $plan)
                ->with('error', "Payment couldn't be started: {$e->getMessage()}");
        }

        $transaction->update([
            'flw_transaction_id' => $charge['id'],
            'meta' => ['instruction' => $charge['instruction']],
        ]);

        if ($charge['redirect_url']) {
            return redirect()->away($charge['redirect_url']);
        }

        // Normal Mobile Money case — client approves on their phone; see
        // FlutterwavePaymentController::waiting().
        return redirect()->route('payments.flutterwave.waiting', $transaction);
    }

    /**
     * Duplicated from SessionRequestController on purpose — same reasoning as
     * routeForStatus()-style duplication elsewhere in this codebase. See that
     * method's comment for why this strips to a LOCAL-only number (no leading 0, no
     * country code) rather than the 256-prefixed format an earlier version used.
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber);

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            return substr($digits, 3);
        }

        return str_starts_with($digits, '0') ? substr($digits, 1) : $digits;
    }

    /**
     * Actually creates the ClientSubscription, once Flutterwave has confirmed a
     * 'subscription' PaymentTransaction succeeded. Called only from
     * FlutterwavePaymentController::finalize() — never directly from a route.
     *
     * Deliberately recomputes proration fresh against whatever the client's active
     * subscriptions look like RIGHT NOW, rather than trusting a value computed back
     * when subscribe() first redirected them to Flutterwave — a real payment can take
     * anywhere from seconds (card) to longer (Mobile Money OTP) to actually confirm.
     * At this app's scale that's a negligible edge case either way, and recomputing
     * fresh is no more code than trusting a stale number would have been.
     *
     * @return array{route: string, params: array, status_message: ?string}
     */
    public function finalizeSubscription(PaymentTransaction $transaction, ?Request $request): array
    {
        $profile = $transaction->clientProfile;
        $plan = $transaction->subscriptionPlan;

        $activeSubscriptions = ClientSubscription::where('client_profile_id', $profile->id)
            ->where('status', 'active')
            ->with('subscriptionPlan')
            ->get();

        $bonusCredits = 0;

        if ($activeSubscriptions->isNotEmpty()) {
            $bonusCredits = $this->prorationFor($activeSubscriptions, $plan)['bonus_credits'];

            ClientSubscription::whereIn('id', $activeSubscriptions->pluck('id'))
                ->update(['status' => 'canceled']);
        }

        $periodEnd = $plan->billing_interval === 'monthly' ? now()->addMonth() : now()->addWeek();

        $subscription = ClientSubscription::create([
            'client_profile_id' => $profile->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'sessions_remaining' => $plan->sessions_included + $bonusCredits,
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
        ]);

        $userName = $profile->user?->name ?? 'A client';
        $event = $activeSubscriptions->isNotEmpty() ? 'client_subscription.self_switched' : 'client_subscription.self_purchased';
        $description = $activeSubscriptions->isNotEmpty()
            ? "{$userName} switched to \"{$plan->name}\" via a real Flutterwave payment (+{$bonusCredits} prorated bonus credit(s))."
            : "{$userName} subscribed to \"{$plan->name}\" via a real Flutterwave payment.";

        AuditLog::record($request, $event, $description, [
            'client_subscription_id' => $subscription->id,
            'payment_transaction_id' => $transaction->id,
        ]);

        $statusMessage = "You're now on {$plan->name}"
            .($bonusCredits > 0 ? " with {$bonusCredits} bonus credit(s) carried over." : '.');

        return [
            'route' => 'subscription.index',
            'params' => [],
            'status_message' => $statusMessage,
        ];
    }

    /**
     * Converts the combined unused value of every currently-active subscription into
     * an equivalent number of credits on $newPlan, using price-per-session as the
     * common unit. Shared by checkout() (to show the client the math before they
     * confirm) and subscribe() (to actually apply it), so what's shown always matches
     * what happens.
     */
    private function prorationFor(Collection $activeSubscriptions, SubscriptionPlan $newPlan): array
    {
        $totalRemainingValue = $activeSubscriptions->sum(function (ClientSubscription $subscription) {
            $plan = $subscription->subscriptionPlan;
            $valuePerSession = ($plan && $plan->sessions_included > 0) ? $plan->price / $plan->sessions_included : 0;

            return $subscription->sessions_remaining * $valuePerSession;
        });

        $newValuePerSession = $newPlan->sessions_included > 0 ? $newPlan->price / $newPlan->sessions_included : 0;

        $bonusCredits = $newValuePerSession > 0 ? (int) floor($totalRemainingValue / $newValuePerSession) : 0;

        return [
            'remaining_sessions' => (int) $activeSubscriptions->sum('sessions_remaining'),
            'remaining_value' => round($totalRemainingValue, 2),
            'bonus_credits' => $bonusCredits,
        ];
    }
}