<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SessionRating;
use App\Models\TherapistProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Session ratings (added 2026-10-04): every rating and comment with names,
 * each therapist's average, and the low ones that may need a follow-up. Ratings are
 * never public; therapists only see their own average (SessionRating::summaryForTherapist).
 */
class RatingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'low' => ['nullable', 'boolean'],
            'therapist' => ['nullable', 'uuid'],
        ]);

        $rated = SessionRating::whereNotNull('rating');

        $overall = [
            'average' => round((float) (clone $rated)->avg('rating'), 1),
            'count' => (clone $rated)->count(),
            'low' => (clone $rated)->where('rating', '<=', SessionRating::LOW)->count(),
            'skipped' => SessionRating::whereNull('rating')->count(),
            'average_30' => round((float) (clone $rated)->where('created_at', '>=', now()->subDays(30))->avg('rating'), 1),
            'count_30' => (clone $rated)->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        // Per therapist (grouped in PHP — small numbers, and avoids DB-specific SQL).
        $byTherapist = SessionRating::whereNotNull('rating')
            ->whereNotNull('therapist_profile_id')
            ->get(['therapist_profile_id', 'rating', 'created_at'])
            ->groupBy('therapist_profile_id');

        $therapistNames = TherapistProfile::with('user')
            ->whereIn('id', $byTherapist->keys())
            ->get()
            ->mapWithKeys(fn ($t) => [$t->id => $t->user?->name ?? 'Therapist']);

        $therapists = $byTherapist->map(fn ($rows, $id) => [
            'id' => $id,
            'name' => $therapistNames[$id] ?? 'Therapist',
            'average' => round((float) $rows->avg('rating'), 1),
            'count' => $rows->count(),
            'low' => $rows->where('rating', '<=', SessionRating::LOW)->count(),
            'average_30' => ($recent = $rows->where('created_at', '>=', now()->subDays(30)))->isNotEmpty()
                ? round((float) $recent->avg('rating'), 1)
                : null,
        ])->sortBy('name')->values();

        $ratings = SessionRating::whereNotNull('rating')
            ->when($filters['low'] ?? false, fn ($q) => $q->where('rating', '<=', SessionRating::LOW))
            ->when($filters['therapist'] ?? null, fn ($q, $id) => $q->where('therapist_profile_id', $id))
            ->with('clientProfile.user', 'therapistProfile.user', 'therapySession')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.ratings.index', [
            'overall' => $overall,
            'therapists' => $therapists,
            'ratings' => $ratings,
            'filters' => $filters,
            'filterTherapistName' => isset($filters['therapist']) ? ($therapistNames[$filters['therapist']] ?? null) : null,
        ]);
    }
}
