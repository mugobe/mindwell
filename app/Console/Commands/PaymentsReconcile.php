<?php

namespace App\Console\Commands;

use App\Http\Controllers\FlutterwavePaymentController;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;

/**
 * The background check for unfinished Mobile Money payments (2026-11-04). Runs every
 * minute (routes/console.php). For each payment that isn't settled yet, asks the payment
 * service what happened:
 *
 *   completed → recorded and applied (or flagged for a refund if it was already paid)
 *   failed    → marked failed
 *   no answer → still waiting; after the deadline the client is told, but we keep
 *               asking for `payments.reconcile_hours`, because a re-sent prompt can
 *               still be approved later.
 *
 * Covers what the screen can't: a closed browser, a weak connection, a late approval,
 * or a callback that never arrives.
 *
 *   php artisan mindwell:payments-reconcile          (what the scheduler runs)
 *   php artisan mindwell:payments-reconcile -v       (one line per payment)
 */
class PaymentsReconcile extends Command
{
    protected $signature = 'mindwell:payments-reconcile';

    protected $description = 'Check unfinished Mobile Money payments with the payment service and settle them';

    public function handle(FlutterwavePaymentController $payments): int
    {
        $open = PaymentTransaction::query()
            ->whereNull('processed_at')
            ->whereNotNull('flw_transaction_id')
            ->where('amount', '>', 0)
            ->where('created_at', '>=', now()->subHours((int) config('payments.reconcile_hours', 24)))
            ->where(fn ($q) => $q->where('status', 'pending')
                ->orWhere(fn ($c) => $c->where('status', 'cancelled')->whereNotNull('meta->closed_reason')))
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        $tally = [];
        foreach ($open as $t) {
            $result = $payments->reconcile($t);
            $tally[$result] = ($tally[$result] ?? 0) + 1;
            if ($this->output->isVerbose()) {
                $this->line($t->created_at->format('d M H:i').'  '.$t->tx_ref.'  '.$t->status.' → '.$result);
            }
        }

        $this->info($open->count().' unfinished payment(s) checked'.($tally ? ': '.collect($tally)->map(fn ($n, $k) => "$n $k")->implode(', ') : '').'.');

        return self::SUCCESS;
    }
}
