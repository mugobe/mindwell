<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmployerSeatController;
use App\Http\Controllers\SubscriptionController;
use App\Models\ClientSubscription;
use App\Models\EmployerSeat;
use App\Models\SubscriptionPlan;
use App\Models\Voucher;
use App\Services\Checkout;
use App\Services\Vouchers;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile API v1 — My plan (buy / switch / renew) and Employer benefit (2026-10-09).
 * Same rules as the website's SubscriptionController and EmployerSeatController; the
 * plan only changes once the payment is confirmed (FlutterwavePaymentController::finalize).
 */
class PlanController extends Controller
{
    private function profile(Request $request)
    {
        return $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);
    }

    private function active($profile)
    {
        ClientSubscription::syncFor($profile->id);

        return ClientSubscription::where('client_profile_id', $profile->id)
            ->where('status', 'active')
            ->with('subscriptionPlan')
            ->get();
    }

    public function index(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $active = $this->active($profile);
        $current = $active->sortByDesc('current_period_start')->first();

        $ended = ! $current
            ? ClientSubscription::where('client_profile_id', $profile->id)->where('status', 'expired')
                ->where('updated_at', '>=', now()->subDays(60))->with('subscriptionPlan')->latest('updated_at')->first()
            : null;

        return response()->json([
            'current' => ApiPresenter::subscription($current),
            'total_credits' => (int) $active->sum('sessions_remaining'),
            'recently_ended' => $ended?->subscriptionPlan ? ApiPresenter::plan($ended->subscriptionPlan) : null,
            'plans' => SubscriptionPlan::orderBy('price')->get()->map(fn ($p) => ApiPresenter::plan($p))->values(),
            'currency' => config('flutterwave.currency'),
        ]);
    }

    /** Price for a plan (?voucher=CODE) plus what carries over from the current plan. */
    public function quote(Request $request, SubscriptionPlan $plan, Vouchers $vouchers): JsonResponse
    {
        $profile = $this->profile($request);
        $active = $this->active($profile);

        $q = $vouchers->quote($request->query('voucher'), Voucher::FOR_PLAN, (float) $plan->price, $profile,
            ['subscription_plan_id' => $plan->id, 'client_subscription_id' => null]);

        return response()->json([
            'plan' => ApiPresenter::plan($plan),
            'quote' => ApiPresenter::quote($q),
            'switching_from' => ApiPresenter::subscription($active->sortByDesc('current_period_start')->first()),
            'proration' => $active->isNotEmpty() ? app(SubscriptionController::class)->prorationFor($active, $plan) : null,
        ]);
    }

    public function subscribe(Request $request, SubscriptionPlan $plan, Checkout $checkout, Vouchers $vouchers): JsonResponse
    {
        $profile = $this->profile($request);
        $target = ['subscription_plan_id' => $plan->id, 'client_subscription_id' => null];

        return $this->pay($request, $checkout, $vouchers, Voucher::FOR_PLAN, (float) $plan->price, $profile, $target, [
            'client_profile_id' => $profile->id,
            'purpose' => 'subscription',
            'subscription_plan_id' => $plan->id,
            'currency' => config('flutterwave.currency'),
        ], 'MW-SUB-');
    }

    public function renewQuote(Request $request, Vouchers $vouchers): JsonResponse
    {
        [$sub, $error] = $this->renewable($request);
        if ($error) {
            return response()->json(['message' => $error], 409);
        }

        $phase = $sub->phase();
        $q = $vouchers->quote($request->query('voucher'), Voucher::FOR_RENEWAL, (float) $sub->subscriptionPlan->price, $sub->clientProfile,
            ['client_subscription_id' => $sub->id]);

        return response()->json([
            'subscription' => ApiPresenter::subscription($sub),
            'in_grace' => $phase === ClientSubscription::PHASE_GRACE,
            'new_period_start' => ($phase === ClientSubscription::PHASE_GRACE ? today() : $sub->current_period_end)?->toDateString(),
            'quote' => ApiPresenter::quote($q),
        ]);
    }

    public function renew(Request $request, Checkout $checkout, Vouchers $vouchers): JsonResponse
    {
        [$sub, $error] = $this->renewable($request);
        if ($error) {
            return response()->json(['message' => $error], 409);
        }

        $plan = $sub->subscriptionPlan;

        return $this->pay($request, $checkout, $vouchers, Voucher::FOR_RENEWAL, (float) $plan->price, $sub->clientProfile,
            ['client_subscription_id' => $sub->id], [
                'client_profile_id' => $sub->client_profile_id,
                'purpose' => 'subscription',
                'subscription_plan_id' => $plan->id,
                'client_subscription_id' => $sub->id,
                'currency' => config('flutterwave.currency'),
            ], 'MW-REN-');
    }

    public function employer(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $seat = EmployerSeat::activeFor($profile->id);
        $seat?->load('employerAccount');

        return response()->json([
            'covered' => (bool) $seat,
            'employer' => $seat?->employerAccount?->company_name,
            'since' => ApiPresenter::date($seat?->assigned_at),
        ]);
    }

    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate(['seat_code' => ['required', 'string', 'max:32']]);

        $result = app(EmployerSeatController::class)->redeemCode($request, $this->profile($request), $validated['seat_code']);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error'], 'errors' => ['seat_code' => [$result['error']]]], 422);
        }

        return response()->json(['message' => $result['message'], 'covered' => true, 'employer' => $result['seat']->employerAccount?->company_name]);
    }

    /** @return array{0: ?ClientSubscription, 1: ?string} */
    private function renewable(Request $request): array
    {
        $profile = $this->profile($request);
        $sub = $this->active($profile)->sortByDesc('current_period_start')->first();

        if (! $sub) {
            return [null, "You don't have an active plan to renew. You can choose a plan instead."];
        }
        if ($sub->renewal_paid_at) {
            return [null, 'Your plan is already renewed — the next period starts on '.$sub->current_period_end->format('D j M').'.'];
        }
        if (! $sub->canRenew() || ! $sub->subscriptionPlan) {
            return [null, "This plan can't be renewed. Please choose a plan instead."];
        }

        return [$sub, null];
    }

    private function pay(Request $request, Checkout $checkout, Vouchers $vouchers, string $purpose, float $price, $profile, array $target, array $base, string $prefix): JsonResponse
    {
        $api = app(CheckoutController::class);
        // A retry after a dropped connection must not send a second PIN prompt.
        $recent = $api->recentPending([
            'client_profile_id' => $base['client_profile_id'],
            'purpose' => 'subscription',
            'subscription_plan_id' => $base['subscription_plan_id'],
            'client_subscription_id' => $base['client_subscription_id'] ?? null,
        ]);
        if ($recent) {
            return $api->alreadySent($recent);
        }

        $code = $request->input('voucher');
        $preview = $vouchers->quote($code, $purpose, $price, $profile, $target);
        if (filled($code) && ! $preview['ok']) {
            return response()->json(['message' => $preview['error'], 'errors' => ['voucher' => [$preview['error']]]], 422);
        }

        $free = $preview['applied'] && $preview['total'] <= 0;
        $momo = $free ? null : $request->validate(Checkout::MOMO_RULES, Checkout::MOMO_MESSAGES);

        $result = $checkout->createTransaction($code, $purpose, $price, $profile, $target, $base, $prefix, $momo);

        return $api->afterCreate($request, $result, $momo, $checkout);
    }
}
