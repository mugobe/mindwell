<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntakeAssessment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntakeReviewController extends Controller
{
    public const RISK_TABS = ['crisis' => 'Crisis', 'high' => 'High', 'moderate' => 'Moderate', 'low' => 'Low'];

    public function index(Request $request): View
    {
        // 2026-10-08 redesign: risk tabs with counts + a name/email search.
        $q = trim((string) $request->query('q', ''));
        $risk = array_key_exists((string) $request->query('risk'), self::RISK_TABS) ? (string) $request->query('risk') : null;

        // Worst risk first, then most recent — this is meant to surface
        // anyone who needs a human's eyes on them, not just a data dump.
        $assessments = IntakeAssessment::with('client.user')
            ->when($risk, fn ($query) => $query->where('risk_level', $risk))
            ->when($q !== '', fn ($query) => $query->whereHas('client.user', fn ($u) => $u
                ->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')))
            ->orderByRaw("FIELD(risk_level, 'crisis', 'high', 'moderate', 'low')")
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = IntakeAssessment::selectRaw('risk_level, count(*) as n')->groupBy('risk_level')->pluck('n', 'risk_level');
        $crisisCount = (int) ($counts['crisis'] ?? 0);
        $highCount = (int) ($counts['high'] ?? 0);
        $tabs = self::RISK_TABS;

        return view('admin.intake.index', compact('assessments', 'crisisCount', 'highCount', 'counts', 'tabs', 'risk', 'q'));
    }
}
