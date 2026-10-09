<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\MindwellNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * Therapist edits their own profile (2026-10-06, roadmap 78 + 67, Dr. Ntabade's
 * THERAPIST DRAFT). Decided with Collins: everything goes live straight away EXCEPT the
 * licence details — a licence change waits for admin approval
 * (Admin\TherapistApprovalController::approveLicense) and the current licence stays in
 * force meanwhile, so the therapist keeps working.
 *
 * Writes use forceFill because the original TherapistProfile model isn't in the
 * assistant's copy of the code.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('therapist.profile.edit', [
            'user' => $request->user(),
            'profile' => $this->profile($request),
            'languages' => \App\Http\Controllers\ClientDetailsController::LANGUAGES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $this->profile($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'professional_title' => ['nullable', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:2000'],
            'languages' => ['nullable', 'string', 'max:255'],
            'specialties' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'license_number' => ['required', 'string', 'max:255'],
            'license_state' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $user->update(['name' => $validated['name']]);

        $specialties = collect(explode(',', $validated['specialties'] ?? ''))->map(fn ($s) => trim($s))->filter()->values()->all();

        $changes = [
            'professional_title' => $validated['professional_title'] ?? null,
            'qualifications' => $validated['qualifications'] ?? null,
            'languages' => collect(explode(',', $validated['languages'] ?? ''))->map(fn ($s) => trim($s))->filter()->implode(', ') ?: null,
            'specialties' => $specialties ?: null,
            'bio' => $validated['bio'] ?? null,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            if ($profile->photo_path) {
                Storage::disk('public')->delete($profile->photo_path);
            }
            $changes['photo_path'] = $request->file('photo')->store('therapist-photos', 'public');
        } elseif ($request->boolean('remove_photo') && $profile->photo_path) {
            Storage::disk('public')->delete($profile->photo_path);
            $changes['photo_path'] = null;
        }

        // Licence details: request a change, don't apply it.
        $licenseChanged = $validated['license_number'] !== $profile->license_number
            || $validated['license_state'] !== $profile->license_state;

        if ($licenseChanged) {
            $changes['pending_license_number'] = $validated['license_number'];
            $changes['pending_license_state'] = $validated['license_state'];
            $changes['license_change_requested_at'] = now();
        }

        $profile->forceFill($changes)->save();

        AuditLog::record($request, 'therapist.profile_updated', $user->name.' updated their profile'.($licenseChanged ? ' and requested a licence change.' : '.'), [
            'therapist_profile_id' => $profile->id,
        ]);

        if ($licenseChanged) {
            $this->tellAdmins($user->name);
        }

        return redirect()->route('therapist.profile.edit')->with('status', $licenseChanged
            ? 'Profile saved. Your licence change has been sent to the Mindwell team for approval — your current licence stays active until then.'
            : 'Profile saved.');
    }

    private function tellAdmins(string $therapistName): void
    {
        foreach (User::where('role', 'admin')->get() as $admin) {
            try {
                $admin->notify(new MindwellNotice(
                    'A therapist licence change needs approval',
                    'Hi '.(trim(explode(' ', (string) $admin->name)[0]) ?: 'there').',',
                    [$therapistName.' has changed their licence details on Mindwell. Please check and approve or reject the change.'],
                    'Open therapist approvals',
                    route('admin.therapists.index'),
                    null,
                ));
            } catch (Throwable $e) {
                Log::warning('Licence-change email failed', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function profile(Request $request)
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $profile->credentials_verified, 403);

        return $profile;
    }
}
