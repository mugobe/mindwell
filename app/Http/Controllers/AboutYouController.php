<?php

namespace App\Http\Controllers;

use App\Models\ClientIntakeDetail;
use App\Support\IntakeQuestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "About you" outside the intake wizard (2026-10-09, roadmap 63). Decided with Collins:
 * clients who did intake before the richer questions existed are asked once on their
 * dashboard ("Tell your therapist a bit about you"); they can fill it in or say "Not now".
 * Anyone can come back here from My details to update their answers.
 */
class AboutYouController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! ClientIntakeDetail::tableReady() || ! IntakeQuestions::anyOn()) {
            return redirect()->route('settings.details');
        }

        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        return view('about.edit', [
            'detail' => ClientIntakeDetail::for($profile->id),
            'sectionsOn' => IntakeQuestions::enabled(),
            'languageDefault' => $request->user()->preferred_language,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(ClientIntakeDetail::tableReady() && IntakeQuestions::anyOn(), 404);

        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);
        $existing = ClientIntakeDetail::for($profile->id);
        // Normalise Windows line breaks so the length limit matches what the browser allowed.
        foreach (['about_own_words', 'about_background', 'about_goals_text'] as $k) {
            if (is_string($request->input($k))) {
                $request->merge([$k => str_replace("\r\n", "\n", $request->input($k))]);
            }
        }
        $validated = $request->validate(IntakeQuestions::rules($existing));

        // saveAbout keeps answers for sections that are switched off now.
        IntakeController::saveAbout($request, $profile, $validated, $existing?->source === 'intake' ? 'intake' : 'later');

        return redirect()->route('dashboard')->with('status', 'Thank you — your therapist will see this before your next session.');
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $profile = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        if (ClientIntakeDetail::tableReady()) {
            // createOrFirst: two quick "Not now" clicks can't trip the unique index.
            ClientIntakeDetail::createOrFirst(
                ['client_profile_id' => $profile->id],
                ['source' => 'later'],
            )->forceFill(['dismissed_at' => now()])->save();
            ClientIntakeDetail::forget($profile->id);
        }

        return redirect()->route('dashboard')->with('status', 'No problem. You can add this any time from My details.');
    }
}
