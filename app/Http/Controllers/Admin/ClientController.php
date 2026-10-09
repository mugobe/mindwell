<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClientProfile;
use App\Services\ClientRecords;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Clients (2026-10-06): every client's list entry and file — the same pages a
 * therapist sees for their own clients. Opening a file is written to the audit log,
 * since it's clinical information.
 */
class ClientController extends Controller
{
    public function index(Request $request, ClientRecords $records): View
    {
        $search = $request->string('q')->trim()->toString();

        return view('clients.index', [
            'viewer' => 'admin',
            'rows' => $records->listFor(null, $search),
            'search' => $search,
            'showRoute' => 'admin.clients.show',
            'searchRoute' => 'admin.clients.index',
        ]);
    }

    public function show(Request $request, ClientProfile $clientProfile, ClientRecords $records): View
    {
        $clientProfile->loadMissing('user');

        AuditLog::record($request, 'client_file.viewed', 'Viewed the client file of '.($clientProfile->user?->name ?? 'a client').'.', [
            'client_profile_id' => $clientProfile->id,
        ]);

        return view('clients.show', $records->file($clientProfile) + [
            'viewer' => 'admin',
            'therapistProfileId' => null,
            'backRoute' => 'admin.clients.index',
            'progressRoute' => 'admin.clients.progress',
        ]);
    }
}
