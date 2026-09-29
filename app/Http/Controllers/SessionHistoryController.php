<?php

namespace App\Http\Controllers;

use App\Models\TherapySession;
use Illuminate\Http\Request;

class SessionHistoryController extends Controller
{
    /**
     * A client's full session record: everything from pending payment /
     * lobby through scheduled, completed, and cancelled sessions, newest
     * first. This is the client-facing counterpart to the therapist's
     * "My sessions" view — read-only, no closure report/outcome shown
     * here (that stays therapist-only for now, per the existing note on
     * the closure feature).
     */
    public function index(Request $request)
    {
        $clientProfile = $request->user()->clientProfile;

        abort_if(! $clientProfile, 403);

        $sessions = TherapySession::where('client_profile_id', $clientProfile->id)
            ->with('therapistProfile.user')
            ->orderByDesc('created_at')
            ->get();

        return view('sessions.history', compact('sessions'));
    }
}