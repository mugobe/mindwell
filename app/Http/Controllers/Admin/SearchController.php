<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin global search (2026-10-08, Doctolib-style "find anyone from anywhere"): the box in
 * the app bar looks up clients (name, email, client ID like 0007), therapists, and
 * payments (receipt number or reference). Read-only.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $results = ['clients' => collect(), 'therapists' => collect(), 'payments' => collect()];

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $digits = preg_replace('/\D/', '', $q);

            $results['clients'] = User::where('role', 'client')
                ->where(function ($w) use ($like, $digits) {
                    $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
                    if ($digits !== '' && strlen($digits) <= 6) {
                        $w->orWhere('client_number', (int) $digits);
                    }
                })
                ->with('clientProfile')->orderBy('name')->limit(15)->get();

            $results['therapists'] = User::where('role', 'therapist')
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->with('therapistProfile')->orderBy('name')->limit(10)->get();

            $results['payments'] = PaymentTransaction::with('clientProfile.user')
                ->where(fn ($w) => $w->where('receipt_number', 'like', $like)->orWhere('tx_ref', 'like', $like))
                ->latest()->limit(10)->get();
        }

        return view('admin.search', [
            'q' => $q,
            'results' => $results,
            'total' => collect($results)->sum(fn ($c) => $c->count()),
        ]);
    }
}
