<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Support\StaffAppearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin → Setup → Appearance (2026-11-03): the staff sidebar colour, one choice for the
 * whole clinic (Collins: navy or the green, "one choice for the clinic").
 */
class AppearanceController extends Controller
{
    public function edit(): View
    {
        return view('admin.appearance.edit', [
            'options' => StaffAppearance::OPTIONS,
            'current' => StaffAppearance::sidebar(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! AppSetting::tableReady()) {
            return back()->withErrors(['sidebar' => 'Run the latest migration first (php artisan migrate), then save again.']);
        }

        $validated = $request->validate([
            'sidebar' => ['required', Rule::in(array_keys(StaffAppearance::OPTIONS))],
        ]);

        AppSetting::put(StaffAppearance::KEY, $validated['sidebar']);

        AuditLog::record($request, 'appearance.updated', 'Changed the staff sidebar colour to '.StaffAppearance::OPTIONS[$validated['sidebar']].'.', [
            'sidebar' => $validated['sidebar'],
        ]);

        return redirect()->route('admin.appearance.edit')->with('status', 'Saved. Every admin and therapist now sees the '.strtolower(StaffAppearance::OPTIONS[$validated['sidebar']]).' sidebar.');
    }
}
