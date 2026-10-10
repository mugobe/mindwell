<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API v1 (2026-10-09): the Android app is for clients only (Collins). Therapists
 * and admins keep using the website. Also refuses suspended accounts.
 */
class EnsureApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'client') {
            return response()->json([
                'message' => 'The Mindwell app is for clients. Therapists and the team use the website.',
                'code' => 'not_client',
            ], 403);
        }

        if (($user->status ?? 'active') === 'suspended') {
            return response()->json([
                'message' => 'This account is suspended. Please contact Mindwell.',
                'code' => 'suspended',
            ], 403);
        }

        return $next($request);
    }
}
