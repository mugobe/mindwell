<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ClientDetailsController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ApiPresenter;
use App\Support\UgandaPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Mobile API v1 — the signed-in client: who am I, My details, notification settings,
 * password, accepting updated Terms (2026-10-09). Same rules as the website pages.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => ApiPresenter::user($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $minAge = (int) config('mindwell.min_client_age', 18);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:'.now()->subYears($minAge)->toDateString()],
            'gender' => ['nullable', 'in:'.implode(',', array_keys(User::GENDERS))],
            'preferred_language' => ['nullable', 'string', 'max:60'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120', 'required_with:emergency_contact_phone'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', UgandaPhone::rule(), 'required_with:emergency_contact_name'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
        ], [
            'date_of_birth.before_or_equal' => "Mindwell accounts are for adults ({$minAge}+).",
        ]);

        $request->user()->update([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'date_of_birth' => $validated['date_of_birth'],
            'gender' => $validated['gender'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => filled($validated['emergency_contact_phone'] ?? null) ? UgandaPhone::normalize($validated['emergency_contact_phone']) : null,
            'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
        ]);

        return response()->json(['message' => 'Your details are saved.', 'user' => ApiPresenter::user($request->user()->fresh())]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:20', UgandaPhone::rule()],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
        ]);

        $request->user()->update([
            'phone' => filled($validated['phone'] ?? null) ? UgandaPhone::normalize($validated['phone']) : null,
            'notify_email' => (bool) ($validated['notify_email'] ?? false),
            'notify_sms' => (bool) ($validated['notify_sms'] ?? false),
        ]);

        return response()->json(['message' => 'Your notification settings are saved.', 'user' => ApiPresenter::user($request->user()->fresh())]);
    }

    public function password(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Choose a password that is different from the current one.',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => "That isn't your current password.", 'errors' => ['current_password' => ["That isn't your current password."]]], 422);
        }

        $wasForced = (bool) $user->must_change_password;

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        // Sign out every OTHER device; this one keeps its token.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current?->id, fn ($q, $id) => $q->where('id', '!=', $id))->delete();

        AuditLog::record($request, 'user.password_changed', $user->name.' changed their password in the app'.($wasForced ? ' (replacing a temporary one).' : '.'), [
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'Your new password is saved.', 'user' => ApiPresenter::user($user->fresh())]);
    }

    public function consent(Request $request): JsonResponse
    {
        $request->validate([
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
            'accept_emergency' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Please agree to the Terms of Use.',
            'accept_privacy.accepted' => 'Please agree to the Privacy Policy.',
            'accept_emergency.accepted' => 'Please confirm you understand Mindwell does not replace emergency care.',
        ]);

        $user = $request->user();
        $user->forceFill(User::consentAttributes(includeEmergency: true))->save();

        AuditLog::record($request, 'user.consent_accepted', $user->name.' accepted Terms/Privacy version '.config('mindwell.legal.version').' in the app.', [
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'Thank you.', 'user' => ApiPresenter::user($user->fresh())]);
    }

    /** Language suggestions for My details (same list as the website). */
    public static function languages(): array
    {
        return ClientDetailsController::LANGUAGES;
    }
}
