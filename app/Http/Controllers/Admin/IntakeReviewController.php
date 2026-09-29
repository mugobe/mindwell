<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntakeAssessment;
use Illuminate\View\View;

class IntakeReviewController extends Controller
{
    public function index(): View
    {
        // Worst risk first, then most recent — this is meant to surface
        // anyone who needs a human's eyes on them, not just a data dump.
        $assessments = IntakeAssessment::with('client.user')
            ->orderByRaw("FIELD(risk_level, 'crisis', 'high', 'moderate', 'low')")
            ->orderByDesc('created_at')
            ->paginate(25);

        $crisisCount = IntakeAssessment::where('risk_level', 'crisis')->count();
        $highCount = IntakeAssessment::where('risk_level', 'high')->count();

        return view('admin.intake.index', compact('assessments', 'crisisCount', 'highCount'));
    }
}