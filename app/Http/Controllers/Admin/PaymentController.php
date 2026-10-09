<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClientSubscription;
use App\Models\PaymentTransaction;
use App\Services\Notifier;
use App\Services\Receipts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Payments (added 2026-10-02): money received through Flutterwave, by month,
 * split into single sessions, new plans and plan renewals, plus every payment attempt
 * for that month and its receipt.
 *
 * Totals only count successful payments. Dates are when the payment was started
 * (Mobile Money approvals land within minutes, so this matches the receipt date in
 * practice).
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:successful,pending,failed,cancelled'],
            'kind' => ['nullable', 'in:session,plan,renewal'],
        ]);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m-d', $validated['month'].'-01')->startOfMonth()
            : now()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $filtered = fn ($query) => $query
            ->when($validated['kind'] ?? null, fn ($q, $kind) => match ($kind) {
                'session' => $q->where('purpose', 'session'),
                'plan' => $q->where('purpose', 'subscription')->whereNull('client_subscription_id'),
                'renewal' => $q->where('purpose', 'subscription')->whereNotNull('client_subscription_id'),
            });

        $payments = $filtered(PaymentTransaction::query())
            ->whereBetween('created_at', [$month, $monthEnd])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->with('clientProfile.user', 'therapySession.therapistProfile.user', 'subscriptionPlan')
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // Month summary (successful only).
        $successfulThisMonth = PaymentTransaction::where('status', 'successful')
            ->whereBetween('created_at', [$month, $monthEnd])
            ->get(['amount', 'refund_amount', 'refunded_at', 'purpose', 'client_subscription_id']);

        // Totals are NET of refunds (2026-10-06).
        $summary = [
            'total' => (float) $successfulThisMonth->sum(fn ($p) => $p->netAmount()),
            'refunded' => (float) $successfulThisMonth->sum(fn ($p) => (float) ($p->refund_amount ?? 0)),
            'count' => $successfulThisMonth->count(),
            'session' => $this->bucket($successfulThisMonth->where('purpose', 'session')),
            'plan' => $this->bucket($successfulThisMonth->where('purpose', 'subscription')->whereNull('client_subscription_id')),
            'renewal' => $this->bucket($successfulThisMonth->where('purpose', 'subscription')->whereNotNull('client_subscription_id')),
        ];

        // Last six months, for the bar chart (grouped in PHP — a few hundred rows at most).
        $sixMonthsStart = $month->copy()->subMonths(5)->startOfMonth();
        $recent = PaymentTransaction::where('status', 'successful')
            ->whereBetween('created_at', [$sixMonthsStart, $monthEnd])
            ->get(['amount', 'refund_amount', 'purpose', 'created_at']);

        $trend = [];
        for ($m = $sixMonthsStart->copy(); $m->lte($month); $m->addMonth()) {
            $inMonth = $recent->filter(fn ($p) => $p->created_at->format('Y-m') === $m->format('Y-m'));
            $trend[] = [
                'label' => $m->format('M'),
                'key' => $m->format('Y-m'),
                'sessions' => (float) $inMonth->where('purpose', 'session')->sum(fn ($p) => $p->netAmount()),
                'plans' => (float) $inMonth->where('purpose', 'subscription')->sum(fn ($p) => $p->netAmount()),
            ];
        }

        // Started more than 30 minutes ago and still not confirmed either way.
        $stuck = PaymentTransaction::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->where('created_at', '>=', now()->subDays(14))
            ->count();

        return view('admin.payments.index', [
            'payments' => $payments,
            'summary' => $summary,
            'trend' => $trend,
            'stuck' => $stuck,
            'month' => $month,
            'filters' => $validated,
            'currency' => config('flutterwave.currency'),
        ]);
    }

    /**
     * Download the month's payments as a spreadsheet (CSV), same filters as the page
     * (2026-10-06, roadmap 24). Opens in Excel / Google Sheets.
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:successful,pending,failed,cancelled'],
            'kind' => ['nullable', 'in:session,plan,renewal'],
        ]);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m-d', $validated['month'].'-01')->startOfMonth()
            : now()->startOfMonth();

        $rows = PaymentTransaction::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['kind'] ?? null, fn ($q, $kind) => match ($kind) {
                'session' => $q->where('purpose', 'session'),
                'plan' => $q->where('purpose', 'subscription')->whereNull('client_subscription_id'),
                'renewal' => $q->where('purpose', 'subscription')->whereNotNull('client_subscription_id'),
            })
            ->with('clientProfile.user', 'therapySession.therapistProfile.user', 'subscriptionPlan')
            ->orderBy('created_at')
            ->get();

        AuditLog::record($request, 'payments.exported', 'Exported '.$rows->count().' payment(s) for '.$month->format('F Y').' to CSV.');

        $filename = 'mindwell-payments-'.$month->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads the UTF-8 correctly
            fputcsv($out, ['Date', 'Receipt', 'Reference', 'Client', 'Client ID', 'Type', 'Description', 'Method', 'Status', 'Currency', 'Amount', 'Refunded', 'Net', 'Refund reason', 'Voucher', 'List price', 'Discount']);
            foreach ($rows as $p) {
                $user = $p->clientProfile?->user;
                fputcsv($out, [
                    $p->created_at->format('Y-m-d H:i'),
                    $p->receipt_number,
                    $p->tx_ref,
                    $user?->name,
                    $user?->clientLabel(),
                    $p->kindLabel(),
                    $p->receiptDescription(),
                    $p->method_label ?? 'Mobile Money',
                    $p->isRefunded() ? ($p->isFullyRefunded() ? 'refunded' : 'partly refunded') : $p->status,
                    $p->currency,
                    number_format((float) $p->amount, 2, '.', ''),
                    number_format((float) ($p->refund_amount ?? 0), 2, '.', ''),
                    number_format($p->status === 'successful' ? $p->netAmount() : 0, 2, '.', ''),
                    $p->refund_reason,
                    $p->voucher?->code,
                    $p->original_amount !== null ? number_format((float) $p->original_amount, 2, '.', '') : '',
                    $p->discount_amount !== null ? number_format((float) $p->discount_amount, 2, '.', '') : '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Record a refund (2026-10-06, roadmap 22). Decided with Collins: the admin sends the
     * money back themselves (Mobile Money / Flutterwave dashboard) and records it here;
     * Mindwell never moves money on its own. Optionally cancels what was paid for.
     */
    public function refund(Request $request, PaymentTransaction $transaction, Notifier $notifier): RedirectResponse
    {
        abort_unless($transaction->status === 'successful', 404);

        if ($transaction->isRefunded()) {
            return back()->with('error', 'That payment has already been refunded.');
        }

        $validated = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:1', 'max:'.(float) $transaction->amount],
            'refund_reason' => ['required', 'string', 'max:500'],
            'cancel_linked' => ['nullable', 'boolean'],
        ]);

        $transaction->forceFill([
            'refunded_at' => now(),
            'refund_amount' => $validated['refund_amount'],
            'refund_reason' => $validated['refund_reason'],
            'refunded_by' => $request->user()->id,
        ])->save();

        $cancelled = $request->boolean('cancel_linked') ? $this->cancelWhatWasPaidFor($transaction) : null;

        AuditLog::record($request, 'payment.refunded', 'Recorded a refund of '.$transaction->currency.' '.number_format((float) $validated['refund_amount'], 0)
            .' on '.($transaction->receipt_number ?? $transaction->tx_ref).($cancelled ? ' and '.$cancelled : '').'. Reason: '.$validated['refund_reason'], [
                'payment_transaction_id' => $transaction->id,
            ]);

        rescue(fn () => $notifier->refundRecorded($transaction), report: true);

        return back()->with('status', 'Refund recorded'.($cancelled ? ' and '.$cancelled : '').'. The client has been emailed.');
    }

    /** @return string|null what was cancelled, for the message */
    private function cancelWhatWasPaidFor(PaymentTransaction $transaction): ?string
    {
        if ($transaction->purpose === 'session') {
            $session = $transaction->therapySession;
            if ($session && in_array($session->status, ['pending_payment', 'routing', 'pending_approval', 'scheduled'], true)) {
                $session->update(['status' => 'cancelled']);

                return 'the session was cancelled';
            }

            return null;
        }

        if ($transaction->isRenewal()) {
            $subscription = $transaction->clientSubscription;
            if ($subscription && $subscription->renewal_paid_at) {
                $subscription->forceFill(['renewal_paid_at' => null])->save();

                return 'the pre-paid renewal was undone';
            }

            return null;
        }

        $subscription = ClientSubscription::where('client_profile_id', $transaction->client_profile_id)
            ->where('subscription_plan_id', $transaction->subscription_plan_id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($subscription) {
            $subscription->update(['status' => 'canceled', 'sessions_remaining' => 0]);

            return 'the plan was ended';
        }

        return null;
    }

    public function receipt(PaymentTransaction $transaction, Receipts $receipts): Response
    {
        abort_unless($transaction->status === 'successful', 404);

        return $receipts->pdf($transaction);
    }

    private function bucket($payments): array
    {
        return ['total' => (float) $payments->sum(fn ($p) => $p->netAmount()), 'count' => $payments->count()];
    }
}
