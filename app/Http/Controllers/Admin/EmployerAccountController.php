<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmployerAccount;
use App\Models\EmployerSeat;
use App\Models\TherapySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Employer account management (2026-09-27) added deactivate()/activate() here and
 * deactivateSeat()/activateSeat()/reissueSeat() below, on top of the original
 * index/create/store/show. Flagged right after employer seat redemption shipped: an
 * employer account's `status` was hardcoded to 'active' at creation and never changed
 * again anywhere in the app, and there was no way to revoke a single client's seat or
 * hand a seat to someone new once it had been redeemed. Follows the same simple
 * status-flip + AuditLog pattern as Admin\UserController::suspend()/activate().
 */
class EmployerAccountController extends Controller
{
    public function index(): View
    {
        $employers = EmployerAccount::withCount('seats')->orderBy('company_name')->get();

        return view('admin.employers.index', compact('employers'));
    }

    public function create(): View
    {
        return view('admin.employers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'seats_purchased' => ['required', 'integer', 'min:1', 'max:1000'],
            'contract_start' => ['nullable', 'date'],
            'contract_renewal_date' => ['nullable', 'date'],
        ]);

        $employer = EmployerAccount::create($validated + ['status' => 'active']);

        // Pre-generate one redeemable seat code per purchased seat, so staff
        // can hand them out to the employer's staff without a second step.
        for ($i = 0; $i < $validated['seats_purchased']; $i++) {
            $employer->seats()->create([
                'seat_code' => strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
                'is_active' => true,
            ]);
        }

        AuditLog::record($request, 'employer_account.created', "Created employer account \"{$employer->company_name}\" with {$validated['seats_purchased']} seats", ['employer_account_id' => $employer->id]);

        return redirect()->route('admin.employers.index')->with('status', $employer->company_name.' was added with '.$validated['seats_purchased'].' seat codes.');
    }

    public function show(EmployerAccount $employer): View
    {
        $employer->load(['seats' => fn ($q) => $q->orderByDesc('created_at')]);

        // How many sessions each redeemed seat's client has taken under THIS benefit
        // specifically (2026-09-27, requested alongside employer seat redemption) --
        // scoped to payment_method 'employer_seat' so a client's separately-paid or
        // subscription-credit sessions don't inflate this employer's count. Done as one
        // grouped query rather than N+1 per seat.
        $clientProfileIds = $employer->seats->pluck('client_profile_id')->filter()->values();

        $sessionCounts = $clientProfileIds->isNotEmpty()
            ? TherapySession::whereIn('client_profile_id', $clientProfileIds)
                ->where('payment_method', 'employer_seat')
                ->selectRaw('client_profile_id, count(*) as aggregate')
                ->groupBy('client_profile_id')
                ->pluck('aggregate', 'client_profile_id')
            : collect();

        $employer->seats->each(function ($seat) use ($sessionCounts) {
            // Left null (rather than 0) for an unredeemed seat, so the view can show a
            // plain "—" instead of a slightly misleading "0 sessions".
            $seat->sessions_had = $seat->client_profile_id ? (int) ($sessionCounts[$seat->client_profile_id] ?? 0) : null;
        });

        return view('admin.employers.show', [
            'employer' => $employer,
            'totalSessionsHad' => (int) $sessionCounts->sum(),
        ]);
    }

    /**
     * Deactivating the employer account is enough on its own to stop EVERY one of its
     * seats from covering a session -- EmployerSeat::activeFor() checks
     * EmployerAccount::isCurrentlyActive() (status === 'active') on every booking, so
     * nothing here needs to also touch the individual seat rows.
     */
    public function deactivate(Request $request, EmployerAccount $employer): RedirectResponse
    {
        $employer->update(['status' => 'inactive']);

        AuditLog::record($request, 'employer_account.deactivated', "Deactivated employer account \"{$employer->company_name}\" -- none of its seats will cover a session until it's reactivated.", ['employer_account_id' => $employer->id]);

        return back()->with('status', "{$employer->company_name} has been deactivated. None of its seats will cover a session until it's reactivated.");
    }

    public function activate(Request $request, EmployerAccount $employer): RedirectResponse
    {
        $employer->update(['status' => 'active']);

        AuditLog::record($request, 'employer_account.reactivated', "Reactivated employer account \"{$employer->company_name}\".", ['employer_account_id' => $employer->id]);

        return back()->with('status', "{$employer->company_name} has been reactivated.");
    }

    /**
     * Cuts off just ONE client's coverage without touching anyone else's seat, or the
     * employer account as a whole -- the narrower counterpart to deactivate() above.
     * The seat stays assigned to this client (so "Assigned to"/"Assigned at" and
     * "Sessions had" still show their history); it just stops passing
     * EmployerSeat::activeFor()'s `is_active` check. Use reissueSeat() below instead if
     * the goal is freeing the seat up for someone else entirely.
     */
    public function deactivateSeat(Request $request, EmployerAccount $employer, EmployerSeat $seat): RedirectResponse
    {
        $this->assertOwnsSeat($employer, $seat);

        $seat->update(['is_active' => false]);

        $who = $seat->clientProfile?->user?->name ?? 'that seat';
        AuditLog::record($request, 'employer_seat.deactivated', "Deactivated seat {$seat->seat_code} for \"{$employer->company_name}\" -- {$who}'s next session will need payment or a subscription credit.", ['employer_seat_id' => $seat->id]);

        return back()->with('status', "Seat {$seat->seat_code} deactivated.");
    }

    public function activateSeat(Request $request, EmployerAccount $employer, EmployerSeat $seat): RedirectResponse
    {
        $this->assertOwnsSeat($employer, $seat);

        $seat->update(['is_active' => true]);

        AuditLog::record($request, 'employer_seat.reactivated', "Reactivated seat {$seat->seat_code} for \"{$employer->company_name}\".", ['employer_seat_id' => $seat->id]);

        return back()->with('status', "Seat {$seat->seat_code} reactivated.");
    }

    /**
     * Frees a seat up for a brand-new person -- clears whoever currently holds it and
     * generates a fresh code, so a leaked or remembered old code can't be redeemed
     * again. Works on a redeemed seat (someone left the company, hand their seat to a
     * new hire) or an unredeemed one (a code was shared/leaked before anyone used it).
     * Deliberately a distinct action from deactivateSeat() above -- pausing someone's
     * access temporarily shouldn't silently invalidate the code they were given.
     */
    public function reissueSeat(Request $request, EmployerAccount $employer, EmployerSeat $seat): RedirectResponse
    {
        $this->assertOwnsSeat($employer, $seat);

        $previousHolder = $seat->clientProfile?->user?->name;
        $newCode = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        $seat->update([
            'client_profile_id' => null,
            'assigned_at' => null,
            'seat_code' => $newCode,
            'is_active' => true,
        ]);

        $description = $previousHolder
            ? "Reissued a seat previously held by {$previousHolder} for \"{$employer->company_name}\" -- their old code no longer works."
            : "Reissued an unredeemed seat for \"{$employer->company_name}\" with a fresh code.";
        AuditLog::record($request, 'employer_seat.reissued', $description, ['employer_seat_id' => $seat->id]);

        // 2026-10-06 (roadmap 26): email the new code to the employer's contact person.
        $emailed = app(\App\Services\Notifier::class)->employerSeatReissued($employer, $newCode);

        return back()->with('status', "New code generated: {$newCode}. "
            .($emailed ? "It's been emailed to {$employer->contact_email}." : 'Hand this to whoever should use the seat next (no contact email on file to send it to).'));
    }

    /**
     * Route model binding resolves {seat} on its own, but doesn't confirm it actually
     * belongs to {employer} in the URL -- without this, an admin could (accidentally or
     * otherwise) pass a valid seat ID alongside the wrong employer's ID in the URL and
     * act on someone else's seat.
     */
    private function assertOwnsSeat(EmployerAccount $employer, EmployerSeat $seat): void
    {
        abort_unless($seat->employer_account_id === $employer->id, 404);
    }
}
