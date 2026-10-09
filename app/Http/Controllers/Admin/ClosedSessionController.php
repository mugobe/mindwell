<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TherapySession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ClosedSessionController extends Controller
{
    /**
     * Read-only admin view of every session a therapist has closed out --
     * the outcome they picked plus their written report. Closes a known
     * gap: that information previously only ever lived on the therapist's
     * own "recently closed" list, invisible to admin.
     */
    public function index(Request $request): View
    {
        // 2026-10-08 redesign: outcome tabs with counts + a name search.
        $q = trim((string) $request->query('q', ''));
        $outcome = array_key_exists((string) $request->query('outcome'), TherapySession::CLOSURE_OUTCOMES)
            ? (string) $request->query('outcome') : null;

        $sessions = TherapySession::where('status', 'completed')
            ->with(['clientProfile.user', 'therapistProfile.user'])
            ->when($outcome, fn ($query) => $query->where('closure_outcome', $outcome))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereHas('clientProfile.user', fn ($u) => $u->where('name', 'like', '%'.$q.'%'))
                ->orWhereHas('therapistProfile.user', fn ($u) => $u->where('name', 'like', '%'.$q.'%'))))
            ->latest('closed_at')
            ->paginate(20)
            ->withQueryString();

        $counts = TherapySession::where('status', 'completed')
            ->selectRaw('closure_outcome, count(*) as n')
            ->groupBy('closure_outcome')
            ->pluck('n', 'closure_outcome');

        return view('admin.closed-sessions.index', [
            'sessions' => $sessions,
            'outcomes' => TherapySession::CLOSURE_OUTCOMES,
            'outcome' => $outcome,
            'counts' => $counts,
            'q' => $q,
        ]);
    }

    public function show(TherapySession $therapySession): View
    {
        abort_unless($therapySession->status === 'completed', 404);

        $therapySession->load(['clientProfile.user', 'therapistProfile.user']);

        return view('admin.closed-sessions.show', [
            'session' => $therapySession,
            'outcomes' => TherapySession::CLOSURE_OUTCOMES,
        ]);
    }

    /**
     * New 2026-09-26 (fourth round of client testing feedback): a downloadable PDF of
     * the report, alongside the on-screen version above. Shares
     * resources/views/reports/pdf.blade.php with Therapist\ReportController::pdf() so
     * admin and therapist download the exact same document.
     */
    public function pdf(TherapySession $therapySession): Response
    {
        abort_unless($therapySession->status === 'completed', 404);

        $therapySession->load(['clientProfile.user', 'therapistProfile.user']);

        $pdf = Pdf::loadView('reports.pdf', [
            'session' => $therapySession,
            'outcomes' => TherapySession::CLOSURE_OUTCOMES,
        ]);

        $clientSlug = Str::slug($therapySession->clientProfile?->user?->name ?? 'client');
        $dateSlug = $therapySession->closed_at?->format('Y-m-d') ?? 'undated';

        return $pdf->download("mindwell-report-{$clientSlug}-{$dateSlug}.pdf");
    }
}
