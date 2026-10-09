<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Please review and accept" screen (Phase 0, 2026-10-05) — for accounts created before
 * the consent boxes existed, and for everyone when the Terms/Privacy version changes.
 * See App\Http\Middleware\EnsureLegalAccepted.
 */
class ConsentController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasAcceptedCurrentLegal()) {
            return redirect()->route('dashboard');
        }

        return view('legal.consent', ['isClient' => $request->user()->role === 'client']);
    }

    public function accept(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isClient = $user->role === 'client';

        $request->validate([
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
            'accept_emergency' => [$isClient ? 'accepted' : 'nullable'],
        ], [
            'accept_terms.accepted' => 'Please agree to the Terms of Use.',
            'accept_privacy.accepted' => 'Please agree to the Privacy Policy.',
            'accept_emergency.accepted' => 'Please confirm you understand Mindwell does not replace emergency care.',
        ]);

        $user->forceFill(User::consentAttributes(includeEmergency: $isClient))->save();

        AuditLog::record($request, 'user.consent_accepted', $user->name.' accepted Terms/Privacy version '.config('mindwell.legal.version').'.', [
            'user_id' => $user->id,
        ]);

        return redirect()->intended(route('dashboard'));
    }
}
