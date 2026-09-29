<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\TherapySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The routing-team queue for "Refer out" closures (2026-09-26 client request): a
 * therapist marking a case as needing rehab/psychiatry/other outside care doesn't pick
 * the facility themselves -- it lands here for staff to assign one of the facilities
 * registered under Admin\FacilityController.
 */
class ReferralController extends Controller
{
    public function index(): View
    {
        $pending = TherapySession::with('clientProfile.user', 'therapistProfile.user')
            ->where('closure_outcome', 'refer_to_specialist')
            ->whereNull('referral_facility_id')
            ->orderBy('referred_at')
            ->get();

        $assigned = TherapySession::with('clientProfile.user', 'therapistProfile.user', 'facility')
            ->where('closure_outcome', 'refer_to_specialist')
            ->whereNotNull('referral_facility_id')
            ->orderByDesc('referral_assigned_at')
            ->limit(20)
            ->get();

        $facilities = Facility::where('is_active', true)->orderBy('name')->get();

        return view('admin.referrals.index', compact('pending', 'assigned', 'facilities'));
    }

    public function assign(Request $request, TherapySession $therapySession): RedirectResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
        ]);

        abort_unless($therapySession->closure_outcome === 'refer_to_specialist', 409, 'This case was not referred out.');
        abort_unless($therapySession->referral_facility_id === null, 409, 'This referral has already been assigned.');

        $therapySession->update([
            'referral_facility_id' => $validated['facility_id'],
            'referral_assigned_at' => now(),
        ]);

        AuditLog::record($request, 'referral.assigned', 'Assigned a referred client to a facility.', [
            'therapy_session_id' => $therapySession->id,
            'facility_id' => $validated['facility_id'],
        ]);

        return back()->with('status', 'Referral assigned.');
    }
}
