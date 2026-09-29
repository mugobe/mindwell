<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClientSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stand-in for a real self-serve "client subscribes to a plan" checkout, which doesn't
 * exist yet — that's a bigger feature (Flutterwave recurring billing, upgrade/downgrade,
 * period rollover) than what was asked for here. This just lets staff grant a client a
 * subscription manually so the session-credit half of the payment flow is actually
 * testable end to end. Period rollover (refilling sessions_remaining when a period ends)
 * is NOT automated — someone has to notice and re-grant for now.
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
        ]);

        $user = User::findOrFail($validated['user_id']);
        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);

        $periodLength = $plan->billing_interval === 'monthly' ? now()->addMonth() : now()->addWeek();

        $subscription = ClientSubscription::create([
            'client_profile_id' => $profile->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'sessions_remaining' => $plan->sessions_included,
            'current_period_start' => now(),
            'current_period_end' => $periodLength,
        ]);

        AuditLog::record($request, 'client_subscription.granted', "Granted \"{$plan->name}\" to {$user->name}.", ['client_subscription_id' => $subscription->id]);

        return redirect()->route('admin.client-subscriptions.index')->with('status', "{$user->name} now has {$plan->sessions_included} session credit(s) on {$plan->name}.");
    }
}
