<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientSubscription;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Models\Voucher;
use App\Services\Checkout;
use App\Services\Vouchers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

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
 *
 * Renewals (added 2026-10-01): renewCheckout()/renew() let a client pay for the next
 * period of the plan they're on — early (it takes effect on the renewal date) or during
 * the 3-day grace period (it starts that day). finalizeRenewal() applies it once
 * Flutterwave confirms. The period rules live on App\Models\ClientSubscription.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->clientProfile;

        if ($profile) {
            // Apply anything due (roll over a renewed plan / expire a lapsed one) first,
            // so this page never shows a plan as active after it has really ended.
            ClientSubscription::syncFor($profile->id);
        }

        $activeSubscriptions = $profile
            ? ClientSubscription::where('client_profile_id', $profile->id)
                ->where('status', 'active')
                ->with('subscriptionPlan')
                ->get()
            : collect();

        $activeSubscription = $activeSubscriptions->sortByDesc('current_period_start')->first();

        // No plan right now: if one ended recently, offer to pick it up again.
        $endedSubscription = (! $activeSubscription && $profile)
            ? ClientSubscription::where('client_profile_id', $profile->id)
                ->where('status', 'expired')
                ->where('updated_at', '>=', now()->subDays(60))
                ->with('subscriptionPlan')
                ->latest('updated_at')
                ->first()
            : null;

        $plans = SubscriptionPlan::orderBy('price')->get();

        return view('subscriptions.index', [
            // Only ever expect one, but see the class docblock on legacy admin-granted
            // duplicates — pick the most recently started one to display as "current."
            'activeSubscription' => $activeSubscription,
            'phase' => $activeSubscription?->phase(),
            'totalCreditsRemaining' => $activeSubscriptions->sum('sessions_remaining'),
            'endedSubscription' => $endedSubscription?->subscriptionPlan ? $endedSubscription : null,
            'plans' => $plans,
        ]);
    }

    public function checkout(Request $request, SubscriptionPlan $plan): View
    {
        $profile = $request->user()->clientProfile;

        if ($profile) {
            ClientSubscription::syncFor($profile->id);
        }

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
            // Vouchers (2026-10-07): ?voucher=CODE previews the discount.
            'quote' => app(Vouchers::class)->quote($request->query('voucher'), Voucher::FOR_PLAN, (float) $plan->price, $profile,
                ['subscription_plan_id' => $plan->id, 'client_subscription_id' => null]),
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

        return $this->payWithVoucher($request, Voucher::FOR_PLAN, (float) $plan->price, $profile, [
            'client_profile_id' => $profile->id,
            'purpose' => 'subscription',
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
            'currency' => config('flutterwave.currency'),
        ], 'MW-SUB-', ['subscription_plan_id' => $plan->id, 'client_subscription_id' => null],
            fn () => redirect()->route('subscription.checkout', $plan));
    }

    /**
     * Renewal checkout (added 2026-10-01): pay for the next period of the plan the client
     * is on now. Only offered while the plan is active and not already renewed.
     */
    public function renewCheckout(Request $request): View|RedirectResponse
    {
        $subscription = $this->renewableSubscription($request);

        if (! $subscription instanceof ClientSubscription) {
            return $subscription;
        }

        $phase = $subscription->phase();

        return view('subscriptions.renew', [
            'subscription' => $subscription,
            'plan' => $subscription->subscriptionPlan,
            'phase' => $phase,
            'inGrace' => $phase === ClientSubscription::PHASE_GRACE,
            'newPeriodStart' => $phase === ClientSubscription::PHASE_GRACE ? today() : $subscription->current_period_end,
            'quote' => app(Vouchers::class)->quote($request->query('voucher'), Voucher::FOR_RENEWAL,
                (float) $subscription->subscriptionPlan->price, $subscription->clientProfile,
                ['client_subscription_id' => $subscription->id]),
        ]);
    }

    public function renew(Request $request): RedirectResponse
    {
        $subscription = $this->renewableSubscription($request);

        if (! $subscription instanceof ClientSubscription) {
            return $subscription;
        }

        $plan = $subscription->subscriptionPlan;

        return $this->payWithVoucher($request, Voucher::FOR_RENEWAL, (float) $plan->price, $subscription->clientProfile, [
            'client_profile_id' => $subscription->client_profile_id,
            'purpose' => 'subscription',
            'subscription_plan_id' => $plan->id,
            'client_subscription_id' => $subscription->id,
            'status' => 'pending',
            'currency' => config('flutterwave.currency'),
        ], 'MW-REN-', ['client_subscription_id' => $subscription->id],
            fn () => redirect()->route('subscription.renew'));
    }

    /** The client's current plan if it can be renewed, otherwise a redirect explaining why not. */
    private function renewableSubscription(Request $request): ClientSubscription|RedirectResponse
    {
        $profile = $request->user()->clientProfile;

        if ($profile) {
            ClientSubscription::syncFor($profile->id);
        }

        $subscription = $profile
            ? ClientSubscription::where('client_profile_id', $profile->id)
                ->where('status', 'active')
                ->with('subscriptionPlan')
                ->orderByDesc('current_period_start')
                ->first()
            : null;

        if (! $subscription) {
            return redirect()->route('subscription.index')
                ->with('error', "You don't have an active plan to renew. You can choose a plan below.");
        }

        if ($subscription->renewal_paid_at) {
            return redirect()->route('subscription.index')
                ->with('status', 'Your plan is already renewed — the next period starts on '.$subscription->current_period_end->format('D j M').'.');
        }

        if (! $subscription->canRenew()) {
            return redirect()->route('subscription.index')
                ->with('error', "This plan can't be renewed. Please choose a plan below.");
        }

        return $subscription;
    }

    /**
     * Vouchers (2026-10-07): checks the code (never trusting the preview), then creates
     * the payment while holding a lock on the voucher so its last use can't be taken
     * twice. A voucher that covers the whole price skips Mobile Money entirely.
     */
    private function payWithVoucher(Request $request, string $voucherPurpose, float $price, $profile, array $base, string $prefix, array $target, \Closure $back): RedirectResponse
    {
        // Since 2026-10-09 the voucher + transaction + Mobile Money steps live in
        // App\Services\Checkout, shared with the mobile API.
        $checkout = app(Checkout::class);
        $code = $request->input('voucher');

        $preview = app(Vouchers::class)->quote($code, $voucherPurpose, $price, $profile, $target);
        if ($request->filled('voucher') && ! $preview['ok']) {
            return $back()->with('error', $preview['error']);
        }

        $free = $preview['applied'] && $preview['total'] <= 0;
        $momo = $free ? null : $request->validate(Checkout::MOMO_RULES, Checkout::MOMO_MESSAGES);

        $result = $checkout->createTransaction($code, $voucherPurpose, $price, $profile, $target, $base, $prefix, $momo);

        if (isset($result['error'])) {
            return $back()->with('error', $result['error']);
        }

        if ($result['free']) {
            return app(FlutterwavePaymentController::class)->completeWithoutCharge($result['transaction'], $request);
        }

        $charge = $checkout->startMobileMoney($result['transaction'], $momo, $request->user());
        if (! $charge['ok']) {
            return $back()->with('error', $charge['error']);
        }

        if ($charge['redirect_url']) {
            return redirect()->away($charge['redirect_url']);
        }

        // Normal Mobile Money case — client approves on their phone; see
        // FlutterwavePaymentController::waiting().
        return redirect()->route('payments.flutterwave.waiting', $result['transaction']);
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

        // Expire anything that has really ended first, so it isn't counted as unused
        // value in the proration below.
        ClientSubscription::syncFor($profile->id);

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

        $subscription = ClientSubscription::create([
            'client_profile_id' => $profile->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'sessions_remaining' => $plan->sessions_included + $bonusCredits,
            'current_period_start' => today(),
            'current_period_end' => $plan->periodEndFrom(today()),
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
     * A renewal payment was confirmed (added 2026-10-01). Called only from
     * FlutterwavePaymentController::finalize(), for a PaymentTransaction with a
     * client_subscription_id.
     *
     *  - Renewed before the renewal date → mark the next period as paid; it starts (and
     *    the credits reset) on the renewal date. Current credits stay usable until then.
     *  - Renewed during the grace period → the new period starts today.
     *  - The plan ended or was replaced while they were paying → they still paid for a
     *    period of this plan, so start a fresh subscription to it (finalizeSubscription()).
     *
     * @return array{route: string, params: array, status_message: ?string}
     */
    public function finalizeRenewal(PaymentTransaction $transaction, ?Request $request): array
    {
        $subscription = $transaction->clientSubscription()->with('subscriptionPlan')->first();
        $subscription?->sync();

        if (! $subscription || $subscription->status !== 'active' || ! $subscription->subscriptionPlan) {
            return $this->finalizeSubscription($transaction, $request);
        }

        $plan = $subscription->subscriptionPlan;
        $userName = $subscription->clientProfile?->user?->name ?? 'A client';

        if ($subscription->renewal_paid_at) {
            // Two renewal payments for the same period — shouldn't happen (renew() refuses
            // once one has gone through), but if two were started at once, flag it for a
            // refund rather than silently swallowing the money.
            AuditLog::record($request, 'client_subscription.duplicate_renewal',
                "{$userName} paid twice to renew \"{$plan->name}\" — the second payment needs a refund.", [
                    'client_subscription_id' => $subscription->id,
                    'payment_transaction_id' => $transaction->id,
                ]);

            return [
                'route' => 'subscription.index',
                'params' => [],
                'status_message' => 'Your plan was already renewed, so this second payment will be refunded. Our team has been notified.',
            ];
        }

        if ($subscription->phase() === ClientSubscription::PHASE_GRACE) {
            $subscription->rollOver(today());

            $message = "Your {$plan->name} plan is renewed. You have {$subscription->sessions_remaining} "
                .($subscription->sessions_remaining === 1 ? 'credit' : 'credits')
                .' until '.$subscription->current_period_end->format('D j M').'.';
        } else {
            $subscription->forceFill(['renewal_paid_at' => now()])->save();

            $message = "Your {$plan->name} plan is renewed. Your next period starts on "
                .$subscription->current_period_end->format('D j M')." with {$plan->sessions_included} "
                .($plan->sessions_included === 1 ? 'credit' : 'credits').'.';
        }

        AuditLog::record($request, 'client_subscription.renewed', "{$userName} renewed \"{$plan->name}\" via a real Flutterwave payment.", [
            'client_subscription_id' => $subscription->id,
            'payment_transaction_id' => $transaction->id,
        ]);

        return [
            'route' => 'subscription.index',
            'params' => [],
            'status_message' => $message,
        ];
    }

    /**
     * Converts the combined unused value of every currently-active subscription into
     * an equivalent number of credits on $newPlan, using price-per-session as the
     * common unit. Shared by checkout() (to show the client the math before they
     * confirm) and subscribe() (to actually apply it), so what's shown always matches
     * what happens.
     */
    public function prorationFor(Collection $activeSubscriptions, SubscriptionPlan $newPlan): array
    {
        $totalRemainingValue = $activeSubscriptions->sum(function (ClientSubscription $subscription) {
            $plan = $subscription->subscriptionPlan;
            $valuePerSession = ($plan && $plan->sessions_included > 0) ? $plan->price / $plan->sessions_included : 0;

            // A next period that's already been paid for (renewed early) counts too, so
            // switching plans never throws that payment away.
            $prepaid = ($plan && $subscription->renewal_paid_at) ? (float) $plan->price : 0;

            return $subscription->sessions_remaining * $valuePerSession + $prepaid;
        });

        $newValuePerSession = $newPlan->sessions_included > 0 ? $newPlan->price / $newPlan->sessions_included : 0;

        $bonusCredits = $newValuePerSession > 0 ? (int) floor($totalRemainingValue / $newValuePerSession) : 0;

        return [
            'remaining_sessions' => (int) $activeSubscriptions->sum('sessions_remaining'),
            'remaining_value' => round($totalRemainingValue, 2),
            'includes_prepaid' => $activeSubscriptions->contains(fn ($sub) => $sub->renewal_paid_at !== null),
            'bonus_credits' => $bonusCredits,
        ];
    }
}
