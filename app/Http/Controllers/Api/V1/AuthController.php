<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClientProfile;
use App\Models\User;
use App\Support\ApiPresenter;
use App\Support\UgandaPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Mobile API v1 — sign-up, log-in, log-out, forgot password (2026-10-09).
 * Tokens are Laravel Sanctum personal access tokens, one per device. Same validation
 * and consent rules as the website's AuthController.
 */
class AuthController extends Controller
{
    /** App sign-ins last 90 days; then the client logs in again. */
    private const TOKEN_DAYS = 90;

    public function register(Request $request): JsonResponse
    {
        $minAge = (int) config('mindwell.min_client_age', 18);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:'.now()->subYears($minAge)->toDateString()],
            'gender' => ['nullable', 'in:'.implode(',', array_keys(User::GENDERS))],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20', UgandaPhone::rule()],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
            'accept_emergency' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'date_of_birth.before_or_equal' => "Mindwell is for adults ({$minAge}+) for now. If you need help right away, use \"Get help now\".",
            'accept_terms.accepted' => 'Please agree to the Terms of Use.',
            'accept_privacy.accepted' => 'Please agree to the Privacy Policy.',
            'accept_emergency.accepted' => 'Please confirm you understand Mindwell does not replace emergency care.',
        ]);

        // The app only ever creates client accounts, like the website's open sign-up.
        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'] ?? null,
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => UgandaPhone::normalize($validated['phone'] ?? null),
                'role' => 'client',
            ] + User::consentAttributes(includeEmergency: true));

            ClientProfile::create(['user_id' => $user->id]);

            return $user;
        });

        rescue(fn () => $user->ensureClientNumber(), report: false);

        $token = $user->createToken($validated['device_name'] ?? 'Android app', ['*'], now()->addDays(self::TOKEN_DAYS))->plainTextToken;

        return response()->json(['token' => $token, 'user' => ApiPresenter::user($user->fresh())], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => "Those details don't match an account.", 'errors' => ['email' => ["Those details don't match an account."]]], 422);
        }

        if ($user->role !== 'client') {
            return response()->json(['message' => 'The Mindwell app is for clients. Therapists and the team use the website.', 'code' => 'not_client'], 403);
        }

        if (($user->status ?? 'active') === 'suspended') {
            return response()->json(['message' => 'This account is suspended. Please contact Mindwell.', 'code' => 'suspended'], 403);
        }

        $token = $user->createToken($validated['device_name'] ?? 'Android app', ['*'], now()->addDays(self::TOKEN_DAYS))->plainTextToken;

        return response()->json(['token' => $token, 'user' => ApiPresenter::user($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        // Same as the website: the reset link opens the website's reset page, and the reply
        // never says whether the email has an account.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => "If there's a Mindwell account for that email, we've sent it a link to reset the password. The link works for 60 minutes — check your spam folder too."]);
    }
}
