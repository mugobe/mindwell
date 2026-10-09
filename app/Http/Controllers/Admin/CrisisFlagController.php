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
    public const STATUS_TABS = ['open' => 'Open', 'acknowledged' => 'Being followed up', 'resolved' => 'Resolved'];

    public function index(Request $request): View
    {
        // 2026-10-08 redesign: status tabs with counts. Default "All" keeps open first.
        $status = array_key_exists((string) $request->query('status'), self::STATUS_TABS) ? (string) $request->query('status') : null;

        $flags = CrisisFlag::with(['client.user', 'intakeAssessment', 'acknowledgedBy'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByRaw("FIELD(status,'open','acknowledged','resolved')")
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = CrisisFlag::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $openCount = (int) ($counts['open'] ?? 0);
        $tabs = self::STATUS_TABS;

        return view('admin.crisis.index', compact('flags', 'openCount', 'counts', 'tabs', 'status'));
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
