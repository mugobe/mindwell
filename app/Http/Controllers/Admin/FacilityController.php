<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The registry of external hospitals/clinics/specialists the routing team can refer a
 * client out to when a therapist closes a session with "Refer out" (2026-09-26).
 * See App\Http\Controllers\Admin\ReferralController for the assignment queue itself.
 */
class FacilityController extends Controller
{
    public function index(): View
    {
        $facilities = Facility::withCount('referrals')->orderBy('name')->get();

        return view('admin.facilities.index', compact('facilities'));
    }

    public function create(): View
    {
        return view('admin.facilities.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Facility::create($this->validated($request));

        return redirect()->route('admin.facilities.index')->with('status', 'Facility added.');
    }

    public function edit(Facility $facility): View
    {
        return view('admin.facilities.edit', compact('facility'));
    }

    public function update(Request $request, Facility $facility): RedirectResponse
    {
        $facility->update($this->validated($request));

        return redirect()->route('admin.facilities.index')->with('status', 'Facility updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'facility_type' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
