<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\ClientProfile;
use App\Models\CrisisFlag;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin view of a client's progress (added 2026-10-03, follow-up to the progress round:
 * admins get the crisis alerts, so they need to see what's behind them). Same page as
 * the therapist's (therapist/progress/show) — scores, bands, crisis flags, history —
 * EXCEPT the daily mood chart: the client shares that with their therapist only.
 *
 * Every view is written to the audit log, since this is clinical data.
 */
class ClientProgressController extends Controller
{
    public function show(Request $request, ClientProfile $clientProfile): View
    {
        $clientProfile->loadMissing('user');

        AuditLog::record($request, 'client_progress.viewed', 'Viewed '.($clientProfile->user?->name ?? 'a client')."'s progress page.", [
            'client_profile_id' => $clientProfile->id,
        ]);

        return view('therapist.progress.show', [
            'client' => $clientProfile,
            'timeline' => CheckIn::timelineFor($clientProfile),
            'openFlags' => CrisisFlag::where('client_id', $clientProfile->id)
                ->whereIn('status', ['open', 'acknowledged'])
                ->latest()
                ->get(),
            'sharesMood' => false,
            'moods' => collect(),
            'moodLabels' => [],
            'status' => CheckIn::statusFor($clientProfile),
            'viewer' => 'admin',
            'backUrl' => url()->previous() !== url()->current() ? url()->previous() : route('admin.crisis.index'),
            'backLabel' => 'Back',
        ]);
    }
}
