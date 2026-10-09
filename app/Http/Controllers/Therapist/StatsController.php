<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Services\TherapistOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** My stats (2026-10-06, roadmap 79 — Dr. Ntabade's THERAPIST DRAFT "Reports"). */
class StatsController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $profile->credentials_verified, 403);

        $overview = new TherapistOverview($profile->id, $request->user());

        return view('therapist.stats', [
            'counts' => $overview->counts(),
            'monthly' => $overview->monthly(),
        ]);
    }
}
