<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 0 (2026-10-05): clients and therapists must have accepted the CURRENT Terms of
 * Use and Privacy Policy (plus, for clients, the "not emergency care" statement).
 * Covers people who signed up before the consent boxes existed, and everyone again
 * whenever config('mindwell.legal.version') changes. Admins are exempt.
 * Applied by class name to the logged-in route groups in routes/web.php.
 */
class EnsureLegalAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'admin' && ! $user->hasAcceptedCurrentLegal()
            && ! $request->routeIs('consent.show', 'consent.accept', 'logout', 'password.change', 'password.change.update',
                // Mobile API (2026-10-09): the app can still read who you are, accept, change
                // password and log out.
                'api.v1.me', 'api.v1.consent', 'api.v1.me.password', 'api.v1.auth.logout')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Please review and accept the updated terms first.', 'code' => 'consent_required'], 403);
            }

            return redirect()->route('consent.show');
        }

        return $next($request);
    }
}
