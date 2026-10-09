<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\Receipts;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The client's "Payments" page (added 2026-10-02): every Mobile Money payment they've
 * made or started, newest first, with a PDF receipt for each successful one.
 *
 * Sessions covered by a plan credit or an employer seat aren't payments, so they don't
 * appear here — they're on My sessions as before.
 */
class PaymentHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->clientProfile;

        $payments = $profile
            ? PaymentTransaction::where('client_profile_id', $profile->id)
                ->with('therapySession.therapistProfile.user', 'subscriptionPlan')
                ->latest()
                ->paginate(20)
            : PaymentTransaction::whereRaw('1 = 0')->paginate(20);

        // Net of any refunds (2026-10-06).
        $paidThisYear = $profile
            ? (float) PaymentTransaction::where('client_profile_id', $profile->id)
                ->where('status', 'successful')
                ->where('created_at', '>=', now()->startOfYear())
                ->get(['amount', 'refund_amount'])
                ->sum(fn ($p) => $p->netAmount())
            : 0;

        return view('payments.index', [
            'payments' => $payments,
            'paidThisYear' => $paidThisYear,
            'currency' => config('flutterwave.currency'),
        ]);
    }

    public function receipt(Request $request, PaymentTransaction $transaction, Receipts $receipts): Response
    {
        abort_unless($transaction->client_profile_id === $request->user()->clientProfile?->id, 403);
        abort_unless($transaction->status === 'successful', 404);

        return $receipts->pdf($transaction);
    }
}
