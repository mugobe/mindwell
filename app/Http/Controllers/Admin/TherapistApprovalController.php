<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TherapistApprovalController extends Controller
{
    public function index(): View
    {
        $pending = TherapistProfile::with('user')->where('credentials_verified', false)->get();
        $verified = TherapistProfile::with('user')->where('credentials_verified', true)->get();

        return view('admin.therapists.index', compact('pending', 'verified'));
    }

    public function approve(Request $request, TherapistProfile $therapist): RedirectResponse
    {
        $therapist->update(['credentials_verified' => true]);

        AuditLog::record($request, 'therapist.approved', $therapist->user->name.' was approved for credentialing.', ['therapist_profile_id' => $therapist->id]);

        return back()->with('status', $therapist->user->name.' has been approved.');
    }

    public function reject(Request $request, TherapistProfile $therapist): RedirectResponse
    {
        $therapist->user->update(['status' => 'suspended']);

        AuditLog::record($request, 'therapist.rejected', $therapist->user->name.' was rejected during credentialing.', ['therapist_profile_id' => $therapist->id]);

        return back()->with('status', $therapist->user->name.' has been rejected.');
    }

    /**
     * Admin-side path #2: staff manually onboard a therapist Dr. Ntabade
     * already knows/has vetted, instead of the therapist self-registering
     * at /register/therapist. Both paths land in the same two tables.
     */
    public function create(): View
    {
        return view('admin.therapists.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'license_number' => ['required', 'string', 'max:255'],
            'license_state' => ['required', 'string', 'max:255'],
            'specialties' => ['nullable', 'string', 'max:500'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'credentials_verified' => ['nullable', 'boolean'],
        ]);

        $specialties = collect(explode(',', $validated['specialties'] ?? ''))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($validated, $specialties, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'therapist',
                'status' => 'active',
            ]);

            TherapistProfile::create([
                'user_id' => $user->id,
                'license_number' => $validated['license_number'],
                'license_state' => $validated['license_state'],
                'specialties' => $specialties ?: null,
                'credentials_verified' => $request->boolean('credentials_verified'),
                'hourly_rate' => $validated['hourly_rate'] ?? null,
            ]);
        });

        AuditLog::record($request, 'therapist.created_by_admin', $validated['name'].' was added directly by an admin.', ['email' => $validated['email']]);

        return redirect()
            ->route('admin.therapists.index')
            ->with('status', $validated['name'].' was added. Share their email and the password you set so they can log in.');
    }
}
