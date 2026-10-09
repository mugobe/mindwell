<?php

namespace App\Services;

use App\Models\CrisisFlag;
use App\Models\PaymentTransaction;
use App\Models\ScheduleChangeRequest;
use App\Models\SessionRating;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Models\TimeOff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data for the admin Overview (2026-10-08 redesign, Doctolib-style): a "needs your
 * attention" work queue, today's agenda across all therapists, and a few headline
 * numbers with a trend. Every query is wrapped so one missing table can't break the page.
 */
class AdminOverview
{
    private function safe(callable $fn, $default = 0)
    {
        return rescue($fn, $default, false);
    }

    /**
     * @return array<int, array{key:string,title:string,sub:string,count:int,route:string,tone:string,icon:string}>
     */
    public function attention(): array
    {
        $items = [
            ['crisis', 'Open crisis flags', 'Someone may be at risk — act first', fn () => CrisisFlag::where('status', 'open')->count(), 'admin.crisis.index', 'urgent', 'alert'],
            ['lobby', 'Clients waiting in the lobby', 'Automatic matching couldn\'t place them — assign a therapist', fn () => TherapySession::where('status', 'routing')->count(), 'admin.session-routing.index', 'urgent', 'route'],
            ['approvals', 'Bookings to approve', 'Confirm or decline new bookings and follow-ups', fn () => TherapySession::where('status', 'pending_approval')->count(), 'admin.session-routing.index', 'warm', 'check'],
            ['schedule', 'Schedule requests', 'Moves, cancellations and time off', fn () => ScheduleChangeRequest::where('status', 'pending')->count() + TimeOff::where('status', 'pending')->count(), 'admin.schedule-requests.index', 'warm', 'swap'],
            ['referrals', 'Referrals to place', 'Closed sessions waiting for a facility', fn () => TherapySession::where('closure_outcome', 'refer_to_specialist')->whereNull('referral_facility_id')->count(), 'admin.referrals.index', 'warm', 'share'],
            ['intake', 'New high-risk intakes', 'Crisis or high-risk screenings in the last 7 days', fn () => \App\Models\IntakeAssessment::whereIn('risk_level', ['crisis', 'high'])->where('created_at', '>=', now()->subDays(7))->count(), 'admin.intake.index', 'warm', 'inbox'],
            ['credentialing', 'Therapists to approve', 'New sign-ups waiting for credential checks', fn () => TherapistProfile::where('credentials_verified', false)->count(), 'admin.therapists.index', 'teal', 'badge'],
            ['licence', 'Licence changes to review', 'Therapists who updated their licence details', fn () => TherapistProfile::whereNotNull('license_change_requested_at')->count(), 'admin.therapists.index', 'teal', 'badge'],
        ];

        $out = [];
        foreach ($items as [$key, $title, $sub, $count, $route, $tone, $icon]) {
            $n = (int) $this->safe($count);
            if ($n > 0) {
                $out[] = compact('key', 'title', 'sub', 'route', 'tone', 'icon') + ['count' => $n];
            }
        }

        return $out;
    }

    /** Today's sessions across every therapist, in time order. */
    public function today(): Collection
    {
        return $this->safe(fn () => TherapySession::with('clientProfile.user', 'therapistProfile.user', 'venue')
            ->whereIn('status', ['scheduled', 'completed', 'pending_approval'])
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->orderBy('scheduled_at')
            ->get(), collect());
    }

    /** Headline numbers, each with last period's figure for a trend. */
    public function kpis(): array
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $lastWeekStart = $weekStart->copy()->subWeek();

        $sessionsIn = fn ($from, $to) => (int) $this->safe(fn () => TherapySession::whereIn('status', ['scheduled', 'completed'])
            ->whereBetween('scheduled_at', [$from, $to])->count());

        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonthNoOverflow();
        // Same number of days into last month, so the comparison is fair mid-month.
        $lastMonthSameDay = $lastMonthStart->copy()->addDays($now->day - 1)->endOfDay()->min($monthStart->copy()->subSecond());

        $netIn = fn ($from, $to) => (float) $this->safe(fn () => PaymentTransaction::where('status', 'successful')
            ->whereBetween('created_at', [$from, $to])
            ->get(['amount', 'refund_amount'])
            ->sum(fn ($p) => max(0, (float) $p->amount - (float) ($p->refund_amount ?? 0))));

        $activeClients = (int) $this->safe(fn () => TherapySession::whereIn('status', ['scheduled', 'completed'])
            ->where('scheduled_at', '>=', $now->copy()->subDays(60))
            ->distinct()->count('client_profile_id'));

        $rating = $this->safe(function () use ($now) {
            $q = SessionRating::whereNotNull('rating')->where('created_at', '>=', $now->copy()->subDays(90));
            $count = (clone $q)->count();

            return [$count ? round((float) $q->avg('rating'), 1) : null, $count];
        }, [null, 0]);

        return [
            'sessions_week' => [$sessionsIn($weekStart, $weekStart->copy()->endOfWeek(Carbon::SUNDAY)), $sessionsIn($lastWeekStart, $weekStart->copy()->subSecond())],
            'revenue_month' => [$netIn($monthStart, $now), $netIn($lastMonthStart, $lastMonthSameDay)],
            'active_clients' => $activeClients,
            'rating' => $rating,
        ];
    }
}
