<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\TherapistApplication;
use App\Models\TherapistApplicationDocument;
use App\Models\TherapistProfile;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → People → Applications (therapist onboarding, 2026-11-03). Every answer and
 * document in one place, a verification checklist, and three decisions:
 *
 *   approve          → fills the therapist profile from the application, verifies them
 *   request changes  → back to the applicant with a note; they edit and resubmit
 *   decline          → closed, with a note
 *
 * The applicant is emailed each time (Notifier::applicationDecided). Documents are only
 * ever streamed from the private disk through document(), never linked directly.
 */
class TherapistApplicationController extends Controller
{
    public const TABS = [
        'submitted' => 'To review',
        'changes_requested' => 'Changes requested',
        'draft' => 'Not submitted yet',
        'approved' => 'Approved',
        'declined' => 'Not approved',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'submitted';

        $counts = TherapistApplication::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $apps = TherapistApplication::with('user', 'documents')
            ->where('status', $tab)
            ->orderByDesc($tab === 'draft' ? 'updated_at' : 'submitted_at')
            ->get();

        return view('admin.applications.index', ['apps' => $apps, 'tab' => $tab, 'tabs' => self::TABS, 'counts' => $counts]);
    }

    public function show(TherapistApplication $application): View
    {
        $application->load('user', 'documents', 'decider', 'therapistProfile');

        return view('admin.applications.show', [
            'app' => $application,
            'consents' => $application->user ? ConsentRecord::currentFor($application->user) : [],
            'missing' => $application->missing(),
            'history' => rescue(fn () => AuditLog::with('user')->where('meta->therapist_application_id', $application->id)->latest()->limit(20)->get(), collect(), false),
        ]);
    }

    public function checklist(Request $request, TherapistApplication $application): RedirectResponse
    {
        $keys = array_keys(config('onboarding.checklist'));
        $request->validate(['items' => ['nullable', 'array'], 'items.*' => [Rule::in($keys)]]);

        $ticked = $request->input('items', []);
        $old = $application->checklist ?? [];
        $new = [];
        foreach ($keys as $key) {
            $done = in_array($key, $ticked, true);
            $new[$key] = $done
                ? ($old[$key]['done'] ?? false ? $old[$key] : ['done' => true, 'by' => $request->user()->name, 'at' => now()->toIso8601String()])
                : ['done' => false];
        }

        $application->update(['checklist' => $new]);

        AuditLog::record($request, 'application.checklist', 'Updated the verification checklist for '.$application->user?->name.'.', [
            'therapist_application_id' => $application->id,
            'done' => array_keys(array_filter($new, fn ($i) => $i['done'])),
        ]);

        return redirect()->route('admin.applications.show', $application)->with('status', 'Checklist saved.');
    }

    public function decide(Request $request, TherapistApplication $application, Notifier $notifier): RedirectResponse
    {
        $v = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'changes', 'decline'])],
            'note' => ['nullable', 'string', 'max:3000', Rule::requiredIf(in_array($request->input('decision'), ['changes', 'decline'], true))],
        ], [
            'note.required' => 'Please write a note for the applicant. They will see it in their email and on their status page.',
        ]);

        abort_unless(in_array($application->status, [TherapistApplication::SUBMITTED, TherapistApplication::CHANGES], true), 422, 'This application has already been decided.');

        if ($v['decision'] === 'approve') {
            if ($out = $application->checklistOutstanding()) {
                return back()->withErrors(['decision' => 'Tick these checklist items before approving: '.implode('; ', $out).'.']);
            }
            if (! $application->isComplete()) {
                return back()->withErrors(['decision' => 'This application is missing answers or documents, so it can\'t be approved yet. Ask for changes instead.']);
            }

            DB::transaction(fn () => $this->approve($application, $request));
        } else {
            $application->forceFill([
                'status' => $v['decision'] === 'changes' ? TherapistApplication::CHANGES : TherapistApplication::DECLINED,
                'decision_note' => $v['note'],
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ])->save();
        }

        $label = ['approve' => 'approved', 'changes' => 'asked for changes to', 'decline' => 'declined'][$v['decision']];
        AuditLog::record($request, 'application.'.$v['decision'], ucfirst($label).' the therapist application from '.$application->user?->name.'.', [
            'therapist_application_id' => $application->id,
            'note' => $v['note'] ?? null,
        ]);

        $notifier->applicationDecided($application->fresh('user'));

        return redirect()->route('admin.applications.show', $application)->with('status', [
            'approve' => $application->user?->name.' is approved. Their profile is live and they can now set their availability.',
            'changes' => 'Sent back to '.$application->user?->name.' with your note.',
            'decline' => 'Declined. '.$application->user?->name.' has been told.',
        ][$v['decision']]);
    }

    /** Copies the application into the therapist profile and verifies the therapist. */
    private function approve(TherapistApplication $app, Request $request): void
    {
        $profile = $app->therapistProfile ?? TherapistProfile::firstOrCreate(
            ['user_id' => $app->user_id],
            ['license_number' => '', 'license_state' => '', 'credentials_verified' => false],
        );

        $level = config('onboarding.qualification_levels.'.$app->answer('training.qualification_level'));
        $qual = trim(implode(', ', array_filter([$app->answer('training.qualification_title'), $app->answer('training.institution')]))
            .($app->answer('training.qualification_year') ? ' ('.$app->answer('training.qualification_year').')' : ''));
        $qualifications = trim(implode("\n", array_filter([
            $level ? $level.': '.$qual : $qual,
            $app->answer('training.other_qualifications'),
        ])));

        $languages = collect((array) $app->answer('training.languages', []))
            ->merge(array_filter(array_map('trim', explode(',', (string) $app->answer('training.languages_other')))))
            ->unique()->implode(', ');

        $profile->forceFill([
            'license_number' => (string) $app->answer('licence.licence_number'),
            'license_state' => (string) $app->regulatorLabel(),
            'professional_title' => $app->answer('profile.professional_title'),
            'qualifications' => $qualifications ?: null,
            'languages' => $languages ?: null,
            'specialties' => array_values((array) $app->answer('training.specialties', [])) ?: null,
            'bio' => $app->answer('profile.bio'),
            'hourly_rate' => $app->answer('profile.fee') !== null && $app->answer('profile.fee') !== '' ? $app->answer('profile.fee') : $profile->hourly_rate,
            'photo_path' => $app->answer('profile.photo_path') ?: $profile->photo_path,
            'offers_in_person' => in_array('in_person', (array) $app->answer('training.session_types', []), true),
            'credentials_verified' => true,
        ])->save();

        $app->forceFill([
            'therapist_profile_id' => $profile->id,
            'status' => TherapistApplication::APPROVED,
            'decision_note' => $request->input('note'),
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ])->save();
    }

    public function document(TherapistApplication $application, TherapistApplicationDocument $document): StreamedResponse
    {
        abort_unless($document->therapist_application_id === $application->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        AuditLog::record(request(), 'application.document_viewed', 'Opened '.$document->label().' for '.$application->user?->name.'.', [
            'therapist_application_id' => $application->id,
            'document' => $document->kind,
        ]);

        $inline = in_array($document->mime, ['application/pdf', 'image/jpeg', 'image/png'], true);

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }
}
