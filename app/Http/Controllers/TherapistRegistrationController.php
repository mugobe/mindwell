<?php

namespace App\Http\Controllers;

use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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
            'license_number' => ['required', 'string', 'max:255'],
            'license_state' => ['required', 'string', 'max:255'],
            'specialties' => ['nullable', 'string', 'max:500'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Turn "Anxiety, CBT, Relationships" into a clean array, or null if left blank.
        $specialties = collect(explode(',', $validated['specialties'] ?? ''))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        $user = DB::transaction(function () use ($validated, $specialties) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'therapist',
            ]);

            TherapistProfile::create([
                'user_id' => $user->id,
                'license_number' => $validated['license_number'],
                'license_state' => $validated['license_state'],
                'specialties' => $specialties ?: null,
                'credentials_verified' => false,
                'hourly_rate' => $validated['hourly_rate'] ?? null,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}