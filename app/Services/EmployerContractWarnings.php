<?php

namespace App\Services;

use App\Models\EmployerAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Employer contract ending in 30 days" email to admins (2026-10-06, roadmap 27). Runs
 * inside `mindwell:notify`. Sent once per renewal date: the stamp counts only if it was
 * set within this warning window, so moving the renewal date later re-arms it.
 */
class EmployerContractWarnings
{
    public function __construct(private Notifier $notifier) {}

    public function run(): int
    {
        $sent = 0;
        $admins = null;

        EmployerAccount::where('status', 'active')
            ->whereNotNull('contract_renewal_date')
            ->whereDate('contract_renewal_date', '>', now()->toDateString())
            ->whereDate('contract_renewal_date', '<=', now()->addDays(EmployerAccount::EXPIRY_WARNING_DAYS)->toDateString())
            ->get()
            ->each(function (EmployerAccount $employer) use (&$sent, &$admins) {
                $windowStart = $employer->contract_renewal_date->copy()->subDays(EmployerAccount::EXPIRY_WARNING_DAYS + 1);
                if ($employer->expiry_warning_sent_at && $employer->expiry_warning_sent_at->gte($windowStart)) {
                    return; // already warned for this renewal date
                }

                try {
                    $employer->forceFill(['expiry_warning_sent_at' => now()])->saveQuietly();
                    $admins ??= User::where('role', 'admin')->get();
                    $this->notifier->employerContractEnding($employer, $admins);
                    $sent++;
                } catch (Throwable $e) {
                    Log::warning('Employer contract warning failed', ['employer_account_id' => $employer->id, 'error' => $e->getMessage()]);
                }
            });

        return $sent;
    }
}
