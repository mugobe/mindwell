<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone logged in with a temporary password an admin gave them (added 2026-10-04)
 * must choose their own before doing anything else. Applied by class name to the
 * logged-in route groups in routes/web.php (no alias needed in bootstrap/app.php).
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password
            && ! $request->routeIs('password.change', 'password.change.update', 'logout',
                'api.v1.me', 'api.v1.me.password', 'api.v1.auth.logout')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Please choose a new password first.', 'code' => 'password_change_required'], 403);
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
