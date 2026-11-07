<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TherapistApplication;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Support\UgandaPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Therapist sign-up, step 0 of onboarding (rebuilt 2026-11-03). This page only creates the
 * account; the licence, training, fitness to practise, profile and consents are asked in
 * TherapistApplicationController, one step at a time, and reviewed by an admin in
 * Admin\TherapistApplicationController before the therapist can see clients.
 */
class TherapistRegistrationController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register-therapist');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required', 'string', 'max:20', UgandaPhone::rule()],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Please agree to the Terms of Use.',
            'accept_privacy.accepted' => 'Please agree to the Privacy Policy.',
            'phone.required' => 'Please add a mobile number. The team uses it to reach you about your application.',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => UgandaPhone::normalize($validated['phone']),
                'role' => 'therapist',
            ] + User::consentAttributes(includeEmergency: false));

            // The licence columns are filled from the application when an admin approves it.
            $profile = TherapistProfile::create([
                'user_id' => $user->id,
                'license_number' => '',
                'license_state' => '',
                'credentials_verified' => false,
            ]);

            TherapistApplication::create([
                'user_id' => $user->id,
                'therapist_profile_id' => $profile->id,
                'status' => TherapistApplication::DRAFT,
                'answers' => [],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::record($request, 'therapist.signed_up', $user->name.' created a therapist account and started an application.', ['user_id' => $user->id]);

        return redirect()->route('apply.step', 'licence')
            ->with('status', 'Your account is ready. Next, tell us about your licence. You can save and come back at any time.');
    }
}
