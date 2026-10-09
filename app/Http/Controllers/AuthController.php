<?php

namespace App\Http\Controllers;

use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $minAge = (int) config('mindwell.min_client_age', 18);

        // Phase 0 (2026-10-05), from Dr. Ntabade's CLIENT DRAFT: first + last name, date
        // of birth, optional gender, and three consent boxes. `name` is still saved
        // ("first last") because the whole app uses it.
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:'.now()->subYears($minAge)->toDateString()],
            'gender' => ['nullable', 'in:'.implode(',', array_keys(User::GENDERS))],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Optional mobile number for SMS reminders (added 2026-09-30).
            'phone' => ['nullable', 'string', 'max:20', \App\Support\UgandaPhone::rule()],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
            'accept_emergency' => ['accepted'],
        ], [
            'date_of_birth.before_or_equal' => "Mindwell is for adults ({$minAge}+) for now. If you need help right away, use \"Get help now\".",
            'accept_terms.accepted' => 'Please agree to the Terms of Use.',
            'accept_privacy.accepted' => 'Please agree to the Privacy Policy.',
            'accept_emergency.accepted' => 'Please confirm you understand Mindwell does not replace emergency care.',
        ]);

        // Public sign-up only ever creates a client account. Therapist and
        // admin accounts are created separately, never through this open form.
        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'] ?? null,
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => \App\Support\UgandaPhone::normalize($validated['phone'] ?? null),
                'role' => 'client',
            ] + User::consentAttributes(includeEmergency: true));

            // Every client gets a client_profiles row immediately — the
            // intake assessment (and everything after it: matching,
            // bookings, journal, reviews) hangs off this, not the user
            // record directly.
            ClientProfile::create(['user_id' => $user->id]);

            return $user;
        });

        // "Client 0001" style ID for the therapist's client file (2026-10-06). Never
        // blocks sign-up — anyone without one gets it the first time it's shown.
        rescue(fn () => $user->ensureClientNumber(), report: false);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Those credentials don\'t match an account.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
