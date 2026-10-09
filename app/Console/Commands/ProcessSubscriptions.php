<?php

namespace App\Console\Commands;

use App\Services\SubscriptionRenewals;
use Illuminate\Console\Command;

/**
 * Runs the subscription renewal checks on their own (added 2026-10-01). The same checks
 * already run every 5 minutes inside `mindwell:notify`, so this is only needed for
 * testing by hand. See App\Services\SubscriptionRenewals.
 */
class ProcessSubscriptions extends Command
{
    protected $signature = 'mindwell:subscriptions';

    protected $description = 'Roll over renewed plans, expire lapsed ones, and send renewal reminders';

    public function handle(SubscriptionRenewals $renewals): int
    {
        $c = $renewals->run();

        $this->info("Rolled into a new period: {$c['rolled']} · expired: {$c['expired']} · "
            ."'renews soon' reminders: {$c['reminders']} · 'due for renewal' notices: {$c['due']} · 'plan ended' notices: {$c['ended']}");

        return self::SUCCESS;
    }
}
