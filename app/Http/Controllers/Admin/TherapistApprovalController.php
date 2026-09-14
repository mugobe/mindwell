<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TherapistProfile;
use Illuminate\Http\RedirectResponse;

class TherapistApprovalController extends Controller
{
    public function index()
    {
        $pending = TherapistProfile::with('user')
            ->where('credentials_verified', false)
            ->orderBy('created_at')
            ->get();

        $verified = TherapistProfile::with('user')
            ->where('credentials_verified', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.therapists.index', compact('pending', 'verified'));
    }

    public function approve(TherapistProfile $therapist): RedirectResponse
    {
        $therapist->update(['credentials_verified' => true]);

        return back()->with('status', "{$therapist->user->name} approved.");
    }

    public function reject(TherapistProfile $therapist): RedirectResponse
    {
        $therapist->user->update(['status' => 'suspended']);

        return back()->with('status', "{$therapist->user->name} rejected.");
    }
}