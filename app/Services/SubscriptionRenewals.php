<?php

namespace App\Services;

use App\Models\ClientSubscription;
use Throwable;
use Illuminate\Support\Facades\Log;

/**
 * The time-based half of subscription renewals (added 2026-10-01). Runs every 5 minutes
 * as part of `mindwell:notify` (so no new scheduler line is needed), and on its own as
 * `mindwell:subscriptions`. Safe to run as often as you like.
 *
 * For every active plan:
 *  1. sync(): roll a prepaid plan into its new period, or expire one whose grace is over.
 *  2. Send the one reminder that's due, once per period:
 *       - "renews on …"        when it's within config('mindwell.subscription_reminder_days')
 *       - "due for renewal"    on/after the renewal date (the grace period)
 *       - "your plan has ended" once it expires
 *
 * See App\Models\ClientSubscription for the rules themselves.
 */
class SubscriptionRenewals
{
    public function __construct(private Notifier $notifier) {}

    /** @return array{rolled:int, expired:int, reminders:int, due:int, ended:int} */
    public function run(): array
    {
        $counts = ['rolled' => 0, 'expired' => 0, 'reminders' => 0, 'due' => 0, 'ended' => 0];

        ClientSubscription::where('status', 'active')
            ->with('subscriptionPlan', 'clientProfile.user')
            ->chunkById(100, function ($subscriptions) use (&$counts) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $this->handle($subscription, $counts);
                    } catch (Throwable $e) {
                        Log::warning('Subscription renewal check failed', ['client_subscription_id' => $subscription->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $counts;
    }

    private function handle(ClientSubscription $subscription, array &$counts): void
    {
        $result = $subscription->sync();

        if ($result === 'rolled') {
            $counts['rolled']++;
        }

        if ($result === 'expired') {
            $counts['expired']++;

            // Don't tell someone their plan "ended" if they've already got another
            // active one (e.g. they switched plans, or an old admin-granted duplicate).
            $hasAnotherPlan = ClientSubscription::where('client_profile_id', $subscription->client_profile_id)
                ->where('status', 'active')
                ->exists();

            if (! $subscription->expired_notice_sent_at && ! $hasAnotherPlan) {
                $this->stamp($subscription, 'expired_notice_sent_at');
                $this->notifier->planExpired($subscription);
                $counts['ended']++;
            }

            return;
        }

        $phase = $subscription->phase();

        if ($phase === ClientSubscription::PHASE_ENDING_SOON && ! $subscription->renewal_reminder_sent_at) {
            $this->stamp($subscription, 'renewal_reminder_sent_at');
            $this->notifier->planRenewsSoon($subscription);
            $counts['reminders']++;
        }

        if ($phase === ClientSubscription::PHASE_GRACE && ! $subscription->renewal_due_sent_at) {
            // Stamp the earlier reminder too, so a plan that skipped straight into its
            // grace period (scheduler was off) doesn't get both in a row.
            $this->stamp($subscription, 'renewal_due_sent_at', 'renewal_reminder_sent_at');
            $this->notifier->planRenewalDue($subscription);
            $counts['due']++;
        }
    }

    private function stamp(ClientSubscription $subscription, string ...$columns): void
    {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $subscription->{$column} ?? now();
        }

        $subscription->forceFill($values)->saveQuietly();
    }
}
