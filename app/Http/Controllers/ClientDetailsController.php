<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UgandaPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → My details (2026-10-06): the client keeps the details their therapist sees
 * in the client file — name, date of birth, gender, preferred language and an emergency
 * contact. Also lets people who signed up before Phase 0 fill in their date of birth.
 */
class ClientDetailsController extends Controller
{
    public const LANGUAGES = ['English', 'Luganda', 'Swahili', 'Runyankole', 'Rukiga', 'Luo', 'Ateso', 'Lusoga', 'Runyoro', 'Lugbara'];

    public function edit(Request $request): View
    {
        return view('settings.details', ['user' => $request->user(), 'languages' => self::LANGUAGES]);
    }

    public function update(Request $request): RedirectResponse
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

        return redirect()->route('settings.details')->with('status', 'Your details are saved.');
    }
}
