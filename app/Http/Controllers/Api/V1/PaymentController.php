<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Receipts;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API v1 — Payments history and PDF receipts (2026-10-09).
 */
class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->clientProfile;

        $page = $profile
            ? PaymentTransaction::where('client_profile_id', $profile->id)
                ->with('therapySession.therapistProfile.user', 'subscriptionPlan')
                ->latest()->paginate(20)
            : null;

        $paidThisYear = $profile
            ? (float) PaymentTransaction::where('client_profile_id', $profile->id)
                ->where('status', 'successful')->where('created_at', '>=', now()->startOfYear())
                ->get(['amount', 'refund_amount'])->sum(fn ($p) => $p->netAmount())
            : 0;

        return response()->json([
            'payments' => $page ? collect($page->items())->map(fn ($p) => ApiPresenter::transaction($p))->values() : [],
            'page' => $page?->currentPage() ?? 1,
            'has_more' => (bool) $page?->hasMorePages(),
            'paid_this_year' => $paidThisYear,
            'currency' => config('flutterwave.currency'),
        ]);
    }

    public function receipt(Request $request, PaymentTransaction $transaction, Receipts $receipts): Response
    {
        abort_unless($transaction->client_profile_id === $request->user()->clientProfile?->id, 403);
        abort_unless($transaction->status === 'successful', 404);

        return $receipts->pdf($transaction);
    }

    /**
     * A 10-minute signed link to the PDF, for the app to open in the phone's browser
     * (a browser download can't send the app's Bearer token). Added 2026-10-09.
     */
    public function receiptLink(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->client_profile_id === $request->user()->clientProfile?->id, 403);
        abort_unless($transaction->status === 'successful', 404);

        $expires = now()->addMinutes(10);

        return response()->json([
            'url' => URL::temporarySignedRoute('receipts.signed', $expires, ['transaction' => $transaction->id]),
            'expires_at' => ApiPresenter::date($expires),
        ]);
    }

    /** The signed link itself (web route `receipts.signed`, no login — the signature is the key). */
    public function signedReceipt(PaymentTransaction $transaction, Receipts $receipts): Response
    {
        abort_unless($transaction->status === 'successful', 404);

        return $receipts->pdf($transaction);
    }
}
