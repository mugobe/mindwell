<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Notifications\MindwellNotice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receipts for successful payments (added 2026-10-02; decided with Collins: email +
 * PDF download, receipt wording "Counselling session" / "Counselling plan").
 *
 *  - issue(): called from FlutterwavePaymentController::finalize() the moment a payment
 *    succeeds — gives it a receipt number and emails the receipt once. Never throws, so
 *    a mail problem can't undo a confirmed payment.
 *  - pdf(): the downloadable receipt, shared by the client's Payments page and admin.
 *
 * Receipt emails always go out, even if the client turned notification emails off —
 * they're a record of money paid, not a reminder. Email only, no SMS.
 */
class Receipts
{
    public function issue(PaymentTransaction $transaction): void
    {
        try {
            $this->ensureNumber($transaction);
            $this->email($transaction);
        } catch (Throwable $e) {
            Log::warning('Receipt could not be issued', ['payment_transaction_id' => $transaction->id, 'error' => $e->getMessage()]);
        }
    }

    /** MW-2026-00001, MW-2026-00002, … per calendar year of payment. */
    public function ensureNumber(PaymentTransaction $transaction): string
    {
        if ($transaction->receipt_number) {
            return $transaction->receipt_number;
        }

        $year = ($transaction->paidAt() ?? now())->format('Y');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $highest = PaymentTransaction::where('receipt_number', 'like', "MW-{$year}-%")->max('receipt_number');
            $next = $highest ? ((int) substr($highest, -5)) + 1 : 1;
            $number = sprintf('MW-%s-%05d', $year, $next);

            try {
                PaymentTransaction::whereKey($transaction->getKey())
                    ->whereNull('receipt_number')
                    ->update(['receipt_number' => $number]);
            } catch (UniqueConstraintViolationException) {
                continue; // someone else just took that number — try the next one
            }

            $transaction->refresh();

            if ($transaction->receipt_number) {
                return $transaction->receipt_number;
            }
        }

        throw new \RuntimeException('Could not assign a receipt number.');
    }

    private function email(PaymentTransaction $transaction): void
    {
        $transaction->loadMissing('clientProfile.user', 'therapySession.therapistProfile.user', 'subscriptionPlan');
        $user = $transaction->clientProfile?->user;

        if (! $user || blank($user->email)) {
            return;
        }

        // Claim the email first (conditional update) so the browser callback and the
        // webhook arriving together can't both send it.
        $claimed = PaymentTransaction::whereKey($transaction->getKey())
            ->whereNull('receipt_emailed_at')
            ->update(['receipt_emailed_at' => now()]);

        if (! $claimed) {
            return;
        }

        $paidAt = $transaction->paidAt() ?? now();
        $amount = $transaction->currency.' '.number_format((float) $transaction->amount, 0);

        $lines = [
            "Thank you — we've received your payment.",
            'Receipt: '.$transaction->receipt_number,
            $transaction->receiptDescription().' — '.$amount,
            'Paid on '.$paidAt->format('M j, Y').($transaction->method_label ? ' by '.$transaction->method_label : '').'.',
        ];

        if ($transaction->isTest()) {
            $lines[] = 'This was a test payment — no real money was taken.';
        }

        $firstName = trim(explode(' ', trim((string) $user->name))[0] ?? '');

        try {
            $user->notify(new MindwellNotice(
                'Your Mindwell receipt '.$transaction->receipt_number,
                'Hi '.($firstName !== '' ? $firstName : 'there').',',
                $lines,
                'View your payments',
                route('payments.index'),
                'Keep this email as your receipt. You can download a PDF copy any time from Payments in Mindwell.',
            ));
        } catch (Throwable $e) {
            // Not retried: the receipt is still on the Payments page (PDF) either way.
            Log::warning('Receipt email failed', ['payment_transaction_id' => $transaction->id, 'error' => $e->getMessage()]);
        }
    }

    public function pdf(PaymentTransaction $transaction): Response
    {
        $this->ensureNumber($transaction);
        $transaction->loadMissing('clientProfile.user', 'therapySession.therapistProfile.user', 'subscriptionPlan');

        return Pdf::loadView('payments.receipt-pdf', [
            'transaction' => $transaction,
            'business' => config('mindwell.receipt'),
        ])->download('mindwell-receipt-'.$transaction->receipt_number.'.pdf');
    }
}
