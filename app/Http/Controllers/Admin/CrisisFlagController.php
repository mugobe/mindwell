<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CrisisFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrisisFlagController extends Controller
{
    public function index(): View
    {
        $flags = CrisisFlag::with(['client.user', 'intakeAssessment', 'acknowledgedBy'])
            ->orderByRaw("FIELD(status,'open','acknowledged','resolved')")
            ->orderByDesc('created_at')
            ->paginate(25);

        $openCount = CrisisFlag::where('status', 'open')->count();

        return view('admin.crisis.index', compact('flags', 'openCount'));
    }

    public function acknowledge(Request $request, CrisisFlag $flag): RedirectResponse
    {
        $flag->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
        ]);

        AuditLog::record($request, 'crisis_flag.acknowledged', 'Acknowledged crisis flag for client', ['crisis_flag_id' => $flag->id]);

        return back()->with('status', 'Marked as acknowledged.');
    }

    public function resolve(Request $request, CrisisFlag $flag): RedirectResponse
    {
        $flag->update([
            'status' => 'resolved',
            'acknowledged_by' => $flag->acknowledged_by ?? $request->user()->id,
            'acknowledged_at' => $flag->acknowledged_at ?? now(),
        ]);

        AuditLog::record($request, 'crisis_flag.resolved', 'Resolved crisis flag for client', ['crisis_flag_id' => $flag->id]);

        return back()->with('status', 'Marked as resolved.');
    }
}
