<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ClientSubscriptionController;
use App\Http\Controllers\Admin\ClosedSessionController;
use App\Http\Controllers\Admin\CrisisFlagController;
use App\Http\Controllers\Admin\EmployerAccountController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\IntakeReviewController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\SessionRoutingController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\TherapistApprovalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployerSeatController;
use App\Http\Controllers\FlutterwavePaymentController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\SessionHistoryController;
use App\Http\Controllers\SessionRequestController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Therapist\AvailabilityController;
use App\Http\Controllers\Therapist\ReportController as TherapistReportController;
use App\Http\Controllers\Therapist\SessionController as TherapistSessionController;
use App\Http\Controllers\TherapistBookingController;
use App\Http\Controllers\TherapistRegistrationController;
use App\Http\Controllers\VideoRoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/register/therapist', [TherapistRegistrationController::class, 'showRegister'])->name('register.therapist');
    Route::post('/register/therapist', [TherapistRegistrationController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Real video sessions (LiveKit), added 2026-09-28. Deliberately in the plain 'auth'
    // group, not a role group: the same page serves a session's client AND its
    // therapist. VideoRoomController checks the user is actually one of those two.
    Route::get('/sessions/{therapySession}/room', [VideoRoomController::class, 'show'])->name('sessions.room');
    Route::get('/sessions/{therapySession}/room/status', [VideoRoomController::class, 'status'])->name('sessions.room.status');
    Route::post('/sessions/{therapySession}/room/token', [VideoRoomController::class, 'token'])->name('sessions.room.token');
    Route::post('/sessions/{therapySession}/room/presence', [VideoRoomController::class, 'presence'])->name('sessions.room.presence');
    Route::post('/sessions/{therapySession}/room/notes', [VideoRoomController::class, 'notes'])->name('sessions.room.notes');
});

Route::middleware(['auth', 'role:client'])->group(function () {
    Route::get('/intake', [IntakeController::class, 'show'])->name('intake.show');
    Route::post('/intake', [IntakeController::class, 'store'])->name('intake.store');
    Route::get('/intake/crisis', [IntakeController::class, 'crisis'])->name('intake.crisis');

    Route::get('/my-sessions', [SessionHistoryController::class, 'index'])->name('sessions.history');

    Route::get('/sessions/{therapySession}/checkout', [SessionRequestController::class, 'checkout'])->name('sessions.checkout');
    Route::post('/sessions/{therapySession}/pay', [SessionRequestController::class, 'pay'])->name('sessions.pay');
    Route::get('/sessions/{therapySession}/lobby', [SessionRequestController::class, 'lobby'])->name('sessions.lobby');
    Route::get('/sessions/{therapySession}/pending-approval', [SessionRequestController::class, 'pendingApproval'])->name('sessions.pending-approval');
    Route::get('/sessions/{therapySession}/status', [SessionRequestController::class, 'status'])->name('sessions.status');
    Route::get('/sessions/{therapySession}/connected', [SessionRequestController::class, 'connected'])->name('sessions.connected');
    Route::post('/sessions/{therapySession}/cancel', [SessionRequestController::class, 'cancel'])->name('sessions.cancel');

    Route::get('/therapists', [TherapistBookingController::class, 'browse'])->name('therapists.browse');
    Route::get('/therapists/{therapist}', [TherapistBookingController::class, 'show'])->name('therapists.book');
    Route::post('/therapists/{therapist}/book', [TherapistBookingController::class, 'book'])->name('therapists.book.store');

    // Self-checkout for subscription plans, added 2026-09-27 — see SubscriptionController
    // docblock for the re-subscribe/proration rules.
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('/subscription/{plan}/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::post('/subscription/{plan}/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');

    // Employer seat redemption, added 2026-09-27 — see EmployerSeatController docblock.
    // Unlike the subscription routes above, this grants no credit count: redeeming just
    // marks a pre-purchased seat as this client's for as long as it (and the employer's
    // contract) stays active — see EmployerSeat::activeFor().
    Route::get('/employer-seat', [EmployerSeatController::class, 'index'])->name('employer-seat.index');
    Route::post('/employer-seat/redeem', [EmployerSeatController::class, 'redeem'])->name('employer-seat.redeem');

    // "My journal", added 2026-09-29 — daily mood entries + the client's notepads from
    // video sessions. Private unless the client shares an entry. See JournalController.
    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
    Route::post('/journal', [JournalController::class, 'store'])->name('journal.store');
    Route::patch('/journal/{entry}', [JournalController::class, 'update'])->name('journal.update');
    Route::delete('/journal/{entry}', [JournalController::class, 'destroy'])->name('journal.destroy');

    // Real Flutterwave integration, added 2026-09-27 — see FlutterwavePaymentController
    // docblock. This is the redirect the client's own browser lands on right after
    // paying (or cancelling) on Flutterwave's hosted checkout page; it's a GET, so it
    // needs no CSRF exception and works fine on a local, non-public URL.
    Route::get('/payments/flutterwave/callback', [FlutterwavePaymentController::class, 'callback'])->name('payments.flutterwave.callback');

    // Added 2026-09-28: the "approve it on your phone" page. v4 Mobile Money has no
    // Flutterwave page to redirect to — see FlutterwavePaymentController::waiting().
    Route::get('/payments/flutterwave/{transaction}/waiting', [FlutterwavePaymentController::class, 'waiting'])->name('payments.flutterwave.waiting');

    // LOCAL SANDBOX TESTING ONLY — 404s unless APP_ENV=local AND the Flutterwave base
    // URL is the sandbox. See FlutterwavePaymentController::simulate().
    Route::post('/payments/flutterwave/{transaction}/simulate', [FlutterwavePaymentController::class, 'simulate'])->name('payments.flutterwave.simulate');
});

/*
 * Flutterwave's server-to-server webhook (2026-09-27) — deliberately OUTSIDE the
 * 'auth' group above, since Flutterwave's servers hold no session/login for this app.
 * It's a bare POST hit directly by Flutterwave, so it also needs a CSRF exception —
 * NOT yet added, since bootstrap/app.php isn't a file this sandbox can see/edit safely
 * (see the project doc's "known workflow constraint"). Add this manually before this
 * route can actually receive anything (harmless to leave unadded for now — there's no
 * public URL yet for Flutterwave to reach anyway):
 *
 *   ->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware) {
 *       $middleware->validateCsrfTokens(except: ['webhooks/flutterwave']);
 *   })
 *
 * (If bootstrap/app.php already has a validateCsrfTokens(except: [...]) call, add
 * 'webhooks/flutterwave' to that existing array instead of adding a second call.)
 */
Route::post('/webhooks/flutterwave', [FlutterwavePaymentController::class, 'webhook'])->name('webhooks.flutterwave');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');

    Route::get('/therapists', [TherapistApprovalController::class, 'index'])->name('therapists.index');
    Route::get('/therapists/create', [TherapistApprovalController::class, 'create'])->name('therapists.create');
    Route::post('/therapists', [TherapistApprovalController::class, 'store'])->name('therapists.store');
    Route::post('/therapists/{therapist}/approve', [TherapistApprovalController::class, 'approve'])->name('therapists.approve');
    Route::post('/therapists/{therapist}/reject', [TherapistApprovalController::class, 'reject'])->name('therapists.reject');

    Route::get('/intake-reviews', [IntakeReviewController::class, 'index'])->name('intake.index');

    Route::get('/crisis-flags', [CrisisFlagController::class, 'index'])->name('crisis.index');
    Route::post('/crisis-flags/{flag}/acknowledge', [CrisisFlagController::class, 'acknowledge'])->name('crisis.acknowledge');
    Route::post('/crisis-flags/{flag}/resolve', [CrisisFlagController::class, 'resolve'])->name('crisis.resolve');

    Route::get('/plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [SubscriptionPlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [SubscriptionPlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [SubscriptionPlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');

    Route::get('/employers', [EmployerAccountController::class, 'index'])->name('employers.index');
    Route::get('/employers/create', [EmployerAccountController::class, 'create'])->name('employers.create');
    Route::post('/employers', [EmployerAccountController::class, 'store'])->name('employers.store');
    Route::get('/employers/{employer}', [EmployerAccountController::class, 'show'])->name('employers.show');

    // Employer account management, added 2026-09-27 — the gap flagged right after
    // employer seat redemption shipped: no way to revoke a seat, deactivate an
    // employer, or reissue a lost/leaked code. See EmployerAccountController docblock.
    Route::post('/employers/{employer}/deactivate', [EmployerAccountController::class, 'deactivate'])->name('employers.deactivate');
    Route::post('/employers/{employer}/activate', [EmployerAccountController::class, 'activate'])->name('employers.activate');
    Route::post('/employers/{employer}/seats/{seat}/deactivate', [EmployerAccountController::class, 'deactivateSeat'])->name('employers.seats.deactivate');
    Route::post('/employers/{employer}/seats/{seat}/activate', [EmployerAccountController::class, 'activateSeat'])->name('employers.seats.activate');
    Route::post('/employers/{employer}/seats/{seat}/reissue', [EmployerAccountController::class, 'reissueSeat'])->name('employers.seats.reissue');

    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');

    Route::get('/session-routing', [SessionRoutingController::class, 'index'])->name('session-routing.index');
    Route::post('/session-routing/{therapySession}/assign', [SessionRoutingController::class, 'assign'])->name('session-routing.assign');
    Route::post('/session-routing/{therapySession}/approve', [SessionRoutingController::class, 'approve'])->name('session-routing.approve');
    Route::post('/session-routing/{therapySession}/decline', [SessionRoutingController::class, 'decline'])->name('session-routing.decline');

    Route::get('/closed-sessions', [ClosedSessionController::class, 'index'])->name('closed-sessions.index');
    Route::get('/closed-sessions/{therapySession}', [ClosedSessionController::class, 'show'])->name('closed-sessions.show');
    Route::get('/closed-sessions/{therapySession}/pdf', [ClosedSessionController::class, 'pdf'])->name('closed-sessions.pdf');

    Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
    Route::get('/facilities/create', [FacilityController::class, 'create'])->name('facilities.create');
    Route::post('/facilities', [FacilityController::class, 'store'])->name('facilities.store');
    Route::get('/facilities/{facility}/edit', [FacilityController::class, 'edit'])->name('facilities.edit');
    Route::put('/facilities/{facility}', [FacilityController::class, 'update'])->name('facilities.update');

    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::post('/referrals/{therapySession}/assign', [ReferralController::class, 'assign'])->name('referrals.assign');

    Route::get('/client-subscriptions', [ClientSubscriptionController::class, 'index'])->name('client-subscriptions.index');
    Route::post('/client-subscriptions', [ClientSubscriptionController::class, 'store'])->name('client-subscriptions.store');
});

Route::middleware(['auth', 'role:therapist'])->prefix('therapist')->name('therapist.')->group(function () {
    Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
    Route::post('/availability', [AvailabilityController::class, 'store'])->name('availability.store');
    Route::delete('/availability/{slot}', [AvailabilityController::class, 'destroy'])->name('availability.destroy');

    Route::get('/sessions', [TherapistSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/{therapySession}/close', [TherapistSessionController::class, 'close'])->name('sessions.close');
    Route::post('/sessions/{therapySession}/close', [TherapistSessionController::class, 'store'])->name('sessions.store');

    Route::get('/reports', [TherapistReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{therapySession}', [TherapistReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{therapySession}/pdf', [TherapistReportController::class, 'pdf'])->name('reports.pdf');
});
