<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\TherapySession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * New 2026-09-26 (fourth round of client testing feedback): a therapist's own closed-
 * session reports used to only be visible 10-at-a-time at the bottom of
 * /therapist/sessions, with no way to search for one or download it. This gives a
 * dedicated, searchable/filterable list of every report a therapist has ever written,
 * plus a PDF export -- resources/views/reports/pdf.blade.php is shared with
 * Admin\ClosedSessionController::pdf() so both sides produce the same document.
 */
class ReportController extends Controller
{
    private function ownProfile(Request $request)
    {
        $profile = $request->user()->therapistProfile;
        abort_unless($profile && $profile->credentials_verified, 403);

        return $profile;
    }

    public function index(Request $request): View
    {
        $profile = $this->ownProfile($request);

        $reports = TherapySession::with('clientProfile.user')
            ->where('therapist_profile_id', $profile->id)
            ->where('status', 'completed')
            ->when($request->filled('outcome'), fn ($q) => $q->where('closure_outcome', $request->string('outcome')))
            ->when($request->filled('client'), fn ($q) => $q->whereHas(
                'clientProfile.user',
                fn ($uq) => $uq->where('name', 'like', '%'.$request->string('client').'%')
            ))
            ->latest('closed_at')
            ->paginate(20)
            ->withQueryString();

        return view('therapist.reports.index', [
            'reports' => $reports,
            'outcomes' => TherapySession::CLOSURE_OUTCOMES,
        ]);
    }

    public function show(Request $request, TherapySession $therapySession): View
    {
        $profile = $this->ownProfile($request);
        abort_unless($therapySession->therapist_profile_id === $profile->id, 403);
        abort_unless($therapySession->status === 'completed', 404);

        $therapySession->load('clientProfile.user', 'therapistProfile.user');

        return view('therapist.reports.show', [
            'session' => $therapySession,
            'outcomes' => TherapySession::CLOSURE_OUTCOMES,
        ]);
    }

    public function pdf(Request $request, TherapySession $therapySession): Response
    {
        $profile = $this->ownProfile($request);
        abort_unless($therapySession->therapist_profile_id === $profile->id, 403);
        abort_unless($therapySession->status === 'completed', 404);

        $therapySession->load('clientProfile.user', 'therapistProfile.user');

        $pdf = Pdf::loadView('reports.pdf', [
            'session' => $therapySession,
            'outcomes' => TherapySession::CLOSURE_OUTCOMES,
        ]);

        $clientSlug = Str::slug($therapySession->clientProfile?->user?->name ?? 'client');
        $dateSlug = $therapySession->closed_at?->format('Y-m-d') ?? 'undated';

        return $pdf->download("mindwell-report-{$clientSlug}-{$dateSlug}.pdf");
    }
}