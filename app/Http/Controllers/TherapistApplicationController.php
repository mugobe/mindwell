<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\TherapistApplication;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Therapist onboarding (2026-11-03), the applicant's side. Six steps:
 *
 *   licence → training → fitness → profile → consents → review (submit)
 *
 * Every step saves as you go ("Save and continue" or "Save and finish later"), so
 * validation here only checks what was typed is well-formed. Whether the application
 * is COMPLETE is TherapistApplication::missing(), checked on the review step.
 * After submitting, the applicant sees status(). Admins review in
 * Admin\TherapistApplicationController.
 */
class TherapistApplicationController extends Controller
{
    private function app(Request $request): TherapistApplication
    {
        $user = $request->user();

        return TherapistApplication::forUser($user)->load('documents', 'user');
    }

    private function steps(): array
    {
        return array_keys(config('onboarding.steps'));
    }

    /** Where to send someone who opens /apply. */
    public function start(Request $request): RedirectResponse
    {
        if ($request->user()->therapistProfile?->credentials_verified) {
            return redirect()->route('dashboard');
        }

        $app = $this->app($request);
        if (! $app->isEditable()) {
            return redirect()->route('apply.status');
        }

        $missing = $app->missing();
        foreach ($this->steps() as $step) {
            if ($step !== 'review' && isset($missing[$step])) {
                return redirect()->route('apply.step', $step);
            }
        }

        return redirect()->route('apply.step', 'review');
    }

    public function show(Request $request, string $step): View|RedirectResponse
    {
        abort_unless(in_array($step, $this->steps(), true), 404);

        if ($request->user()->therapistProfile?->credentials_verified) {
            return redirect()->route('dashboard');
        }

        $app = $this->app($request);
        if (! $app->isEditable()) {
            return redirect()->route('apply.status');
        }

        return view('apply.step', [
            'app' => $app,
            'step' => $step,
            'steps' => config('onboarding.steps'),
            'missing' => $app->missing(),
            'consents' => ConsentRecord::currentFor($request->user()),
        ]);
    }

    public function save(Request $request, string $step): RedirectResponse
    {
        abort_unless(in_array($step, $this->steps(), true) && $step !== 'review', 404);

        $app = $this->app($request);
        if (! $app->isEditable()) {
            return redirect()->route('apply.status');
        }

        match ($step) {
            'licence' => $this->saveLicence($request, $app),
            'training' => $this->saveTraining($request, $app),
            'fitness' => $this->saveFitness($request, $app),
            'profile' => $this->saveProfile($request, $app),
            'consents' => $this->saveConsents($request),
        };

        $app->save();

        if ($request->input('go') === 'later') {
            return redirect()->route('apply.step', $step)->with('status', 'Saved. You can log out and finish later; everything you entered is kept.');
        }

        if ($request->input('go') === 'back') {
            $prev = $this->steps()[max(0, array_search($step, $this->steps(), true) - 1)];

            return redirect()->route('apply.step', $prev);
        }

        $next = $this->steps()[min(count($this->steps()) - 1, array_search($step, $this->steps(), true) + 1)];

        return redirect()->route('apply.step', $next);
    }

    private function saveLicence(Request $request, TherapistApplication $app): void
    {
        $v = $request->validate([
            'profession' => ['nullable', Rule::in(array_keys(config('onboarding.professions')))],
            'profession_other' => ['nullable', 'string', 'max:120'],
            'regulator' => ['nullable', Rule::in(array_keys(config('onboarding.regulators')))],
            'regulator_other' => ['nullable', 'string', 'max:160'],
            'licence_number' => ['nullable', 'string', 'max:80'],
            'licence_issued' => ['nullable', 'date', 'before_or_equal:today'],
            'licence_expiry' => ['nullable', 'date', 'after:today'],
            'doc_licence' => $this->fileRule(),
            'doc_national_id' => $this->fileRule(),
        ], [
            'licence_expiry.after' => 'Your licence must still be in date. If it has expired, renew it first and then come back.',
            'licence_issued.before_or_equal' => 'The issue date can\'t be in the future.',
        ]);

        $app->setStep('licence', collect($v)->except(['doc_licence', 'doc_national_id'])->all());
        $this->storeDocs($request, $app, ['licence', 'national_id']);
    }

    private function saveTraining(Request $request, TherapistApplication $app): void
    {
        $list = fn () => ['nullable', 'array'];
        $v = $request->validate([
            'qualification_level' => ['nullable', Rule::in(array_keys(config('onboarding.qualification_levels')))],
            'qualification_title' => ['nullable', 'string', 'max:160'],
            'institution' => ['nullable', 'string', 'max:160'],
            'qualification_year' => ['nullable', 'integer', 'min:1960', 'max:'.now()->year],
            'other_qualifications' => ['nullable', 'string', 'max:2000'],
            'years_practice' => ['nullable', 'integer', 'min:0', 'max:60'],
            'supervision' => ['nullable', Rule::in(['yes', 'no'])],
            'supervisor_name' => ['nullable', 'string', 'max:120'],
            'supervisor_contact' => ['nullable', 'string', 'max:160'],
            'specialties' => $list(),
            'specialties.*' => [Rule::in(config('onboarding.specialties'))],
            'approaches' => $list(),
            'approaches.*' => [Rule::in(config('onboarding.approaches'))],
            'languages' => $list(),
            'languages.*' => [Rule::in(config('onboarding.languages'))],
            'languages_other' => ['nullable', 'string', 'max:160'],
            'age_groups' => $list(),
            'age_groups.*' => [Rule::in(array_keys(config('onboarding.age_groups')))],
            'session_types' => $list(),
            'session_types.*' => [Rule::in(array_keys(config('onboarding.session_types')))],
            'doc_cv' => $this->fileRule(),
        ]);

        // Unticking every box must clear the list, so missing arrays become [].
        foreach (['specialties', 'approaches', 'languages', 'age_groups', 'session_types'] as $k) {
            $v[$k] = array_values($v[$k] ?? []);
        }

        $app->setStep('training', collect($v)->except('doc_cv')->all());
        $this->storeDocs($request, $app, ['cv']);
    }

    private function saveFitness(Request $request, TherapistApplication $app): void
    {
        $rules = [
            'insurance' => ['nullable', Rule::in(['yes', 'no'])],
            'insurer' => ['nullable', 'string', 'max:160'],
            'insurance_expiry' => ['nullable', 'date'],
            'references' => ['nullable', 'array', 'size:2'],
            'references.*.name' => ['nullable', 'string', 'max:120'],
            'references.*.role' => ['nullable', 'string', 'max:120'],
            'references.*.organisation' => ['nullable', 'string', 'max:160'],
            'references.*.relationship' => ['nullable', 'string', 'max:160'],
            'references.*.email' => ['nullable', 'email', 'max:160'],
            'references.*.phone' => ['nullable', 'string', 'max:30'],
            'doc_insurance' => $this->fileRule(),
        ];
        foreach (array_keys(config('onboarding.declarations')) as $key) {
            $rules["declarations.$key.answer"] = ['nullable', Rule::in(['yes', 'no'])];
            $rules["declarations.$key.details"] = ['nullable', 'string', 'max:2000'];
        }

        $v = $request->validate($rules, [
            'references.*.email.email' => 'Each reference email must be a valid email address.',
        ]);

        // The two referees must be different people from the applicant.
        $own = strtolower((string) $request->user()->email);
        foreach ($v['references'] ?? [] as $i => $ref) {
            if ($own && strtolower((string) ($ref['email'] ?? '')) === $own) {
                throw ValidationException::withMessages(["references.$i.email" => 'A reference must be someone else, not you.']);
            }
        }

        $app->setStep('fitness', collect($v)->except('doc_insurance')->all());
        $this->storeDocs($request, $app, ['insurance']);
    }

    private function saveProfile(Request $request, TherapistApplication $app): void
    {
        $v = $request->validate([
            'professional_title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1500'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $values = collect($v)->except('photo')->all();

        if ($request->hasFile('photo')) {
            if ($old = $app->answer('profile.photo_path')) {
                Storage::disk('public')->delete($old);
            }
            $values['photo_path'] = $request->file('photo')->store('therapist-photos', 'public');
        }

        $app->setStep('profile', $values);
    }

    private function saveConsents(Request $request): void
    {
        $docs = config('onboarding.consents');
        $rules = [];
        foreach (array_keys($docs) as $key) {
            $rules["agree.$key"] = ['nullable', 'accepted'];
        }
        $request->validate($rules);

        foreach ($docs as $key => $doc) {
            if ($request->boolean("agree.$key")) {
                ConsentRecord::record($request->user(), $key, $doc['version'], $request);
            }
        }
    }

    private function fileRule(): array
    {
        return ['nullable', 'file', 'mimes:'.config('onboarding.upload.mimes'), 'max:'.config('onboarding.upload.max_kb')];
    }

    /** Saves uploads on the PRIVATE disk; a new file replaces the old one of the same kind. */
    private function storeDocs(Request $request, TherapistApplication $app, array $kinds): void
    {
        foreach ($kinds as $kind) {
            $file = $request->file('doc_'.$kind);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if (! $app->exists) {
                $app->save();
            }

            $path = $file->storeAs(
                'therapist-applications/'.$app->id,
                $kind.'-'.Str::random(10).'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension()),
                'local',
            );

            $old = $app->documents()->where('kind', $kind)->first();
            if ($old) {
                Storage::disk('local')->delete($old->path);
                $old->delete();
            }

            $app->documents()->create([
                'kind' => $kind,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $app->load('documents');
    }

    public function removeDocument(Request $request, string $kind): RedirectResponse
    {
        $app = $this->app($request);
        abort_unless($app->isEditable(), 403);

        $doc = $app->documents()->where('kind', $kind)->first();
        if ($doc) {
            Storage::disk('local')->delete($doc->path);
            $doc->delete();
        }

        return back()->with('status', 'File removed.');
    }

    public function submit(Request $request, Notifier $notifier): RedirectResponse
    {
        $app = $this->app($request);
        if (! $app->isEditable()) {
            return redirect()->route('apply.status');
        }

        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Please confirm that everything in your application is true and complete.']);

        if (! $app->isComplete()) {
            return redirect()->route('apply.step', 'review')->with('error', 'A few things are still missing. They are listed below.');
        }

        $resubmit = $app->status === TherapistApplication::CHANGES;

        $app->forceFill([
            'status' => TherapistApplication::SUBMITTED,
            'submitted_at' => now(),
            'submissions' => $app->submissions + 1,
        ])->save();

        AuditLog::record($request, $resubmit ? 'application.resubmitted' : 'application.submitted',
            $request->user()->name.($resubmit ? ' resubmitted' : ' submitted').' their therapist application.', ['therapist_application_id' => $app->id]);

        $notifier->applicationSubmitted($app, User::where('role', 'admin')->get(), $resubmit);

        return redirect()->route('apply.status')->with('status', 'Application sent. We\'ll email you as soon as it has been reviewed.');
    }

    public function status(Request $request): View|RedirectResponse
    {
        if ($request->user()->therapistProfile?->credentials_verified) {
            return redirect()->route('dashboard');
        }

        $app = $this->app($request);

        return view('apply.status', ['app' => $app, 'consents' => ConsentRecord::currentFor($request->user())]);
    }
}
