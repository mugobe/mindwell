<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Support\IntakeQuestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Setup → Intake questions (2026-10-09, roadmap 63). Switch each "About you"
 * section on or off and edit the topic and goal lists. Changes apply to the next client.
 */
class IntakeSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.intake-settings.edit', [
            'sections' => IntakeQuestions::SECTIONS,
            'switches' => IntakeQuestions::switches(),
            'topics' => IntakeQuestions::topics(),
            'goals' => IntakeQuestions::goals(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! AppSetting::tableReady()) {
            return back()->withErrors(['sections' => 'Run the latest migration first (php artisan migrate), then save again.'])->withInput();
        }

        $validated = $request->validate([
            'sections' => ['nullable', 'array'],
            'sections.*' => ['in:1'],
            'topics' => ['nullable', 'string', 'max:3000'],
            'goals' => ['nullable', 'string', 'max:3000'],
        ]);

        $switches = [];
        foreach (array_keys(IntakeQuestions::SECTIONS) as $key) {
            $switches[$key] = isset($validated['sections'][$key]);
        }

        $topics = IntakeQuestions::linesToList($validated['topics'] ?? '');
        $goals = IntakeQuestions::linesToList($validated['goals'] ?? '');

        if ($switches['topics'] && $topics === []) {
            return back()->withErrors(['topics' => 'Add at least one topic, or switch "What brings you here" off.'])->withInput();
        }
        if ($switches['goals'] && $goals === []) {
            return back()->withErrors(['goals' => 'Add at least one goal, or switch "Goals" off.'])->withInput();
        }

        AppSetting::put('intake.sections', $switches);
        // Empty list = use the built-in defaults next time the section is switched on.
        AppSetting::put('intake.topics', $topics ?: null);
        AppSetting::put('intake.goals', $goals ?: null);

        AuditLog::record($request, 'intake_settings.updated', 'Changed the intake "About you" questions.', [
            'sections' => $switches,
            'topics' => count($topics),
            'goals' => count($goals),
        ]);

        return redirect()->route('admin.intake-settings.edit')->with('status', 'Intake questions saved. They apply to the next client.');
    }
}
