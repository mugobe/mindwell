<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Venues (2026-10-07, roadmap 68): where in-person sessions happen. Picked by
 * admin when approving an in-person booking (Admin\SessionRoutingController).
 */
class VenueController extends Controller
{
    public function index(): View
    {
        $venues = Venue::withCount(['sessions' => fn ($q) => $q->where('status', 'scheduled')->where('scheduled_at', '>=', now())])
            ->orderByDesc('is_active')->orderBy('name')->get();

        return view('admin.venues.index', compact('venues'));
    }

    public function create(): View
    {
        return view('admin.venues.form', ['venue' => new Venue(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = Venue::create($this->validated($request));
        AuditLog::record($request, 'venue.created', "Added venue {$venue->name}.", ['venue_id' => $venue->id]);

        return redirect()->route('admin.venues.index')->with('status', 'Venue added.');
    }

    public function edit(Venue $venue): View
    {
        return view('admin.venues.form', compact('venue'));
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $venue->update($this->validated($request));
        AuditLog::record($request, 'venue.updated', "Updated venue {$venue->name}.", ['venue_id' => $venue->id]);

        return redirect()->route('admin.venues.index')->with('status', 'Venue saved.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'directions' => ['nullable', 'string', 'max:2000'],
            'map_url' => ['nullable', 'url', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
