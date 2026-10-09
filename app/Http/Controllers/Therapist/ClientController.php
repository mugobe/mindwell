<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Models\TherapySession;
use App\Services\ClientRecords;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My clients" (2026-10-06, roadmap 71). A therapist only ever sees clients they've
 * had (or have) a session with — decided with Collins. Journal text and mood stay
 * private unless the client shares them.
 */
class ClientController extends Controller
{
    public function index(Request $request, ClientRecords $records): View
    {
        $therapist = $this->therapist($request);
        $search = $request->string('q')->trim()->toString();

        return view('clients.index', [
            'viewer' => 'therapist',
            'rows' => $records->listFor($therapist->id, $search),
            'search' => $search,
            'showRoute' => 'therapist.clients.show',
            'searchRoute' => 'therapist.clients.index',
        ]);
    }

    public function show(Request $request, ClientProfile $clientProfile, ClientRecords $records): View
    {
        $therapist = $this->therapist($request);
        abort_unless(
            TherapySession::where('client_profile_id', $clientProfile->id)->where('therapist_profile_id', $therapist->id)->exists(),
            403
        );

        return view('clients.show', $records->file($clientProfile, $therapist->id) + [
            'viewer' => 'therapist',
            'therapistProfileId' => $therapist->id,
            'backRoute' => 'therapist.clients.index',
            'progressRoute' => 'therapist.clients.progress',
        ]);
    }

    private function therapist(Request $request)
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $profile->credentials_verified, 403);

        return $profile;
    }
}
