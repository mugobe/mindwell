<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API v1 (2026-10-09): every /api request is treated as wanting JSON, even if the
 * app forgets "Accept: application/json". Matters because a few API routes reuse website
 * actions that answer JSON only when asked (ratings, messages) and would otherwise send
 * a redirect — the app would then think it failed and send the message twice.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
