<?php

namespace App\Console\Commands;

use App\Models\ClientSubscription;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * LOCAL TESTING ONLY (added 2026-10-01). Plans run for a week or a month, which is too
 * long to sit and wait for when testing renewals, so this moves a client's current plan
 * N days into the past — exactly as if N days had gone by. Then run
 * `php artisan mindwell:subscriptions` (or reload My plan) to see what happens.
 *
 *     php artisan mindwell:plan-time-travel joseph@example.com 5
 *
 * Refuses to run unless APP_ENV=local.
 */
class SubscriptionTimeTravel extends Command
{
    protected $signature = 'mindwell:plan-time-travel {email : The client\'s login email} {days : How many days to move the plan back}';

    protected $description = 'LOCAL ONLY: pretend N days have passed on a client\'s current plan';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This only runs when APP_ENV=local.');

            return self::FAILURE;
        }

        $days = (int) $this->argument('days');
        if ($days < 1 || $days > 60) {
            $this->error('Days must be between 1 and 60.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();
        $profileId = $user?->clientProfile?->id;
        if (! $profileId) {
            $this->error('No client with that email.');

            return self::FAILURE;
        }

        $subscription = ClientSubscription::where('client_profile_id', $profileId)
            ->where('status', 'active')
            ->orderByDesc('current_period_start')
            ->first();

        if (! $subscription) {
            $this->error('That client has no active plan.');

            return self::FAILURE;
        }

        $subscription->forceFill([
            'current_period_start' => $subscription->current_period_start->copy()->subDays($days),
            'current_period_end' => $subscription->current_period_end->copy()->subDays($days),
        ])->saveQuietly();

        $subscription->refresh();

        $this->info("Moved {$user->name}'s plan back {$days} day(s).");
        $this->line('Period now: '.$subscription->current_period_start->format('D j M').' → renews '.$subscription->current_period_end->format('D j M'));
        $this->line('Phase today: '.$subscription->phase().'   (run `php artisan mindwell:subscriptions` to apply it)');

        return self::SUCCESS;
    }
}
