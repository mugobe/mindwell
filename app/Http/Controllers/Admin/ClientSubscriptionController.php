<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Http\Controllers\SubscriptionController;
use App\Models\ClientSubscription;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\Receipts;
use Illuminate\View\View;

/**
 * Stand-in for a real self-serve "client subscribes to a plan" checkout, which doesn't
 * exist yet — that's a bigger feature (Flutterwave recurring billing, upgrade/downgrade,
 * period rollover) than what was asked for here. This just lets staff grant a client a
 * subscription manually so the session-credit half of the payment flow is actually
 * testable end to end. Clients can now subscribe and renew themselves (My plan); this
 * stays as a manual grant for staff (e.g. a plan paid for in cash). Since 2026-10-01 a
 * granted plan follows the same renewal rules as any other — see ClientSubscription.
 */
class ClientSubscriptionController extends Controller
{
    public function index(): View
    {
        $subscriptions = ClientSubscription::with('clientProfile.user', 'subscriptionPlan')
            ->latest()
            ->get();

        $clients = User::where('role', 'client')->orderBy('name')->get();
        $plans = SubscriptionPlan::orderBy('name')->get();

        return view('admin.client-subscriptions.index', compact('subscriptions', 'clients', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            // 2026-10-06 (roadmap 23): a plan can be granted free, or recorded as paid in cash.
            'payment_type' => ['nullable', 'in:complimentary,cash'],
            'cash_amount' => ['nullable', 'required_if:payment_type,cash', 'numeric', 'min:0'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);

        if (($validated['payment_type'] ?? 'complimentary') === 'cash') {
            // Same path as a real payment: a PaymentTransaction, then the normal "new plan"
            // logic (replaces any active plan, carries unused credit over), then a receipt.
            $transaction = $this->recordCash($request, $profile->id, $plan, (float) $validated['cash_amount']);
            app(SubscriptionController::class)->finalizeSubscription($transaction, $request);
            app(Receipts::class)->issue($transaction);

            return redirect()->route('admin.client-subscriptions.index')
                ->with('status', "{$user->name} is now on {$plan->name}, paid in cash. Receipt {$transaction->fresh()->receipt_number} has been emailed.");
        }

        $subscription = ClientSubscription::create([
            'client_profile_id' => $profile->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'sessions_remaining' => $plan->sessions_included,
            'current_period_start' => today(),
            'current_period_end' => $plan->periodEndFrom(today()),
        ]);

        AuditLog::record($request, 'client_subscription.granted', "Granted \"{$plan->name}\" to {$user->name} (complimentary).", ['client_subscription_id' => $subscription->id]);

        return redirect()->route('admin.client-subscriptions.index')->with('status', "{$user->name} now has {$plan->sessions_included} session credit(s) on {$plan->name}.");
    }

    /**
     * Renew a client's current plan when they pay cash (2026-10-06, roadmap 23). Uses the
     * exact same renewal rules as a Mobile Money renewal (SubscriptionController::
     * finalizeRenewal): early = next period pre-paid; in grace = new period starts today.
     */
    public function renewCash(Request $request, ClientSubscription $subscription): RedirectResponse
    {
        $subscription->sync();
        abort_unless($subscription->canRenew(), 409, 'This plan can\'t be renewed (it has ended or is already renewed).');

        $validated = $request->validate(['cash_amount' => ['required', 'numeric', 'min:0']]);

        $plan = $subscription->subscriptionPlan;
        $transaction = $this->recordCash($request, $subscription->client_profile_id, $plan, (float) $validated['cash_amount'], $subscription->id);

        $result = app(SubscriptionController::class)->finalizeRenewal($transaction, $request);
        app(Receipts::class)->issue($transaction);

        return back()->with('status', 'Cash renewal recorded. '.($result['status_message'] ?? '').' Receipt '.$transaction->fresh()->receipt_number.' has been emailed.');
    }

    private function recordCash(Request $request, string $clientProfileId, SubscriptionPlan $plan, float $amount, ?string $clientSubscriptionId = null): PaymentTransaction
    {
        $transaction = PaymentTransaction::create([
            'client_profile_id' => $clientProfileId,
            'purpose' => 'subscription',
            'subscription_plan_id' => $plan->id,
            'client_subscription_id' => $clientSubscriptionId,
            'tx_ref' => 'MW-CASH-'.strtoupper(Str::random(10)),
            'status' => 'successful',
            'amount' => $amount,
            'currency' => config('flutterwave.currency'),
            'method_label' => 'Cash (recorded by '.$request->user()->name.')',
            'meta' => ['cash' => true, 'recorded_by' => $request->user()->id],
            'processed_at' => now(),
        ]);

        AuditLog::record($request, 'payment.cash_recorded', 'Recorded a cash payment of '.$transaction->currency.' '.number_format($amount, 0).' for "'.$plan->name.'"'.($clientSubscriptionId ? ' (renewal).' : ' (new plan).'), [
            'payment_transaction_id' => $transaction->id,
        ]);

        return $transaction;
    }
}
