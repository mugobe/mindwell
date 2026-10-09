<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\ClientProfile;
use App\Models\TherapySession;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Check-ins (2026-10-06, roadmap 28): for every client in care (a session in the
 * last 90 days or upcoming), are they keeping up with their two-weekly check-in?
 * Rules are the same as everywhere else — see App\Models\CheckIn::statusFor().
 */
class CheckInOverviewController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate(['status' => ['nullable', 'in:overdue,due,up_to_date,no_intake']])['status'] ?? null;
        $delay = (int) config('mindwell.check_in_reminder_delay_days', 2);

        $rows = ClientProfile::with('user')
            ->whereIn('id', CheckIn::inCareProfileIds()->all())
            ->get()
            ->filter(fn ($p) => $p->user)
            ->map(function ($profile) use ($delay) {
                $status = CheckIn::statusFor($profile);
                $latest = CheckIn::timelineFor($profile)->last();
                $checkIns = CheckIn::where('client_profile_id', $profile->id)->count();
                $therapist = TherapySession::where('client_profile_id', $profile->id)
                    ->whereNotNull('therapist_profile_id')
                    ->with('therapistProfile.user')
                    ->latest('scheduled_at')
                    ->first()?->therapistProfile?->user?->name;

                $key = match (true) {
                    ! $status['eligible'] => 'no_intake',
                    $status['due'] && $status['overdue_days'] >= $delay => 'overdue',
                    $status['due'] => 'due',
                    default => 'up_to_date',
                };

                return compact('profile', 'status', 'latest', 'checkIns', 'therapist', 'key');
            });

        $counts = $rows->countBy('key');

        $order = ['overdue' => 0, 'due' => 1, 'no_intake' => 2, 'up_to_date' => 3];
        $shown = $rows
            ->when($filter, fn ($c) => $c->where('key', $filter))
            ->sortBy([
                fn ($a, $b) => $order[$a['key']] <=> $order[$b['key']],
                fn ($a, $b) => ($b['status']['overdue_days'] ?? 0) <=> ($a['status']['overdue_days'] ?? 0),
            ])
            ->values();

        return view('admin.check-ins.index', [
            'rows' => $shown,
            'counts' => $counts,
            'total' => $rows->count(),
            'filter' => $filter,
        ]);
    }
}
