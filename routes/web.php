<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CheckInOverviewController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\ClientProgressController as AdminClientProgressController;
use App\Http\Controllers\Admin\ClientSubscriptionController;
use App\Http\Controllers\Admin\ClosedSessionController;
use App\Http\Controllers\Admin\CrisisFlagController;
use App\Http\Controllers\Admin\EmployerAccountController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\CalendarController as AdminCalendarController;
use App\Http\Controllers\Admin\ScheduleRequestController;
use App\Http\Controllers\Admin\IntakeReviewController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\RatingController as AdminRatingController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\SessionRoutingController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\TherapistApprovalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientDetailsController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\EmployerSeatController;
use App\Http\Controllers\FlutterwavePaymentController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Middleware\EnsureLegalAccepted;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\SessionHistoryController;
use App\Http\Controllers\SessionChangeController;
use App\Http\Controllers\SessionRatingController;
use App\Http\Controllers\SessionRequestController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Therapist\AvailabilityController;
use App\Http\Controllers\Therapist\CalendarController as TherapistCalendarController;
use App\Http\Controllers\Therapist\ClientController as TherapistClientController;
use App\Http\Controllers\Therapist\ProfileController as TherapistProfileController;
use App\Http\Controllers\Therapist\StatsController as TherapistStatsController;
use App\Http\Controllers\Therapist\ClientProgressController;
use App\Http\Controllers\Therapist\ReportController as TherapistReportController;
use App\Http\Controllers\Therapist\SessionController as TherapistSessionController;
use App\Http\Controllers\TherapistBookingController;
use App\Http\Controllers\TherapistRegistrationController;
use App\Http\Controllers\VideoRoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Public pages (Phase 0, 2026-10-05): urgent help works WITHOUT an account — from
// Dr. Ntabade's CLIENT DRAFT — and the Terms / Privacy pages linked from sign-up.
Route::view('/urgent-help', 'urgent-help')->name('urgent-help');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/register/therapist', [TherapistRegistrationController::class, 'showRegister'])->name('register.therapist');
    Route::post('/register/therapist', [TherapistRegistrationController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Forgot password (2026-10-04) — see PasswordResetController.
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', EnsurePasswordChanged::class, EnsureLegalAccepted::class])->group(function () {
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

    // Client ↔ therapist messaging, added 2026-09-29 — both roles, so the plain 'auth'
    // group; MessageController checks the user is one of the two people involved.
    // /messages/unread and /messages/with/... must stay above /messages/{conversation}.
    // Notification settings (phone for SMS, email/SMS switches), added 2026-09-30.
    Route::get('/settings/notifications', [NotificationSettingsController::class, 'edit'])->name('settings.notifications');
    Route::put('/settings/notifications', [NotificationSettingsController::class, 'update'])->name('settings.notifications.update');
    Route::post('/settings/notifications/test-sms', [NotificationSettingsController::class, 'testSms'])->middleware('throttle:3,10')->name('settings.notifications.test-sms');

    // Change password (2026-10-04). Also where EnsurePasswordChanged sends someone who
    // logged in with a temporary password from an admin.
    Route::get('/settings/password', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/settings/password', [PasswordChangeController::class, 'update'])->middleware('throttle:6,1')->name('password.change.update');

    // Terms / Privacy acceptance for existing accounts (Phase 0) — see EnsureLegalAccepted.
    Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
    Route::post('/consent', [ConsentController::class, 'accept'])->name('consent.accept');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/unread', [MessageController::class, 'unread'])->name('messages.unread');
    Route::get('/messages/with/{profileId}', [MessageController::class, 'start'])->name('messages.start');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [MessageController::class, 'send'])->middleware('throttle:30,1')->name('messages.send');
    Route::get('/messages/{conversation}/poll', [MessageController::class, 'poll'])->name('messages.poll');
});

Route::middleware(['auth', EnsurePasswordChanged::class, EnsureLegalAccepted::class, 'role:client'])->group(function () {
    Route::get('/intake', [IntakeController::class, 'show'])->name('intake.show');
    Route::post('/intake', [IntakeController::class, 'store'])->name('intake.store');
    Route::get('/intake/crisis', [IntakeController::class, 'crisis'])->name('intake.crisis');

    Route::get('/my-sessions', [SessionHistoryController::class, 'index'])->name('sessions.history');

    // Payment history & receipts (2026-10-02) — see PaymentHistoryController.
    Route::get('/payments', [PaymentHistoryController::class, 'index'])->name('payments.index');
    Route::get('/payments/{transaction}/receipt', [PaymentHistoryController::class, 'receipt'])->name('payments.receipt');

    // Progress & mood insights (2026-10-03) — see ProgressController / App\Models\CheckIn.
    Route::get('/progress', [ProgressController::class, 'index'])->name('progress.index');
    Route::get('/progress/check-in', [ProgressController::class, 'checkIn'])->name('progress.check-in');
    Route::post('/progress/check-in', [ProgressController::class, 'store'])->name('progress.check-in.store');
    Route::get('/progress/support', [ProgressController::class, 'crisis'])->name('progress.crisis');
    Route::post('/progress/share-mood', [ProgressController::class, 'shareMood'])->name('progress.share-mood');

    // Settings → My details (2026-10-06): what the therapist sees in the client file.
    Route::get('/settings/details', [ClientDetailsController::class, 'edit'])->name('settings.details');
    Route::put('/settings/details', [ClientDetailsController::class, 'update'])->name('settings.details.update');

    // Richer intake (2026-10-09, roadmap 63): "About you" for clients who did intake before it existed,
    // and to update answers later. See AboutYouController.
    Route::get('/about-you', [\App\Http\Controllers\AboutYouController::class, 'edit'])->name('about.edit');
    Route::put('/about-you', [\App\Http\Controllers\AboutYouController::class, 'update'])->name('about.update');
    Route::post('/about-you/not-now', [\App\Http\Controllers\AboutYouController::class, 'dismiss'])->name('about.dismiss');

    // Session feedback & ratings (2026-10-04) — see SessionRatingController.
    Route::get('/sessions/{therapySession}/rate', [SessionRatingController::class, 'show'])->name('sessions.rate');
    Route::post('/sessions/{therapySession}/rate', [SessionRatingController::class, 'store'])->middleware('throttle:10,1')->name('sessions.rate.store');

    Route::get('/sessions/{therapySession}/checkout', [SessionRequestController::class, 'checkout'])->name('sessions.checkout');
    Route::post('/sessions/{therapySession}/pay', [SessionRequestController::class, 'pay'])->name('sessions.pay');
    Route::get('/sessions/{therapySession}/lobby', [SessionRequestController::class, 'lobby'])->name('sessions.lobby');
    Route::get('/sessions/{therapySession}/pending-approval', [SessionRequestController::class, 'pendingApproval'])->name('sessions.pending-approval');
    Route::get('/sessions/{therapySession}/status', [SessionRequestController::class, 'status'])->name('sessions.status');
    Route::get('/sessions/{therapySession}/connected', [SessionRequestController::class, 'connected'])->name('sessions.connected');
    Route::post('/sessions/{therapySession}/cancel', [SessionRequestController::class, 'cancel'])->name('sessions.cancel');
    // Calendar (2026-10-08): ask to move or cancel a confirmed session; admin approves.
    Route::get('/sessions/{therapySession}/change', [SessionChangeController::class, 'show'])->name('sessions.change');
    Route::post('/sessions/{therapySession}/change', [SessionChangeController::class, 'store'])->middleware('throttle:10,1')->name('sessions.change.store');
    Route::post('/change-requests/{changeRequest}/withdraw', [SessionChangeController::class, 'withdraw'])->name('sessions.change.withdraw');

    Route::get('/therapists', [TherapistBookingController::class, 'browse'])->name('therapists.browse');
    Route::get('/therapists/{therapist}', [TherapistBookingController::class, 'show'])->name('therapists.book');
    Route::post('/therapists/{therapist}/book', [TherapistBookingController::class, 'book'])->name('therapists.book.store');

    // Self-checkout for subscription plans, added 2026-09-27 — see SubscriptionController
    // docblock for the re-subscribe/proration rules.
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('/subscription/{plan}/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::post('/subscription/{plan}/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
    // Renewals (2026-10-01) — pay for the next period of the current plan.
    Route::get('/subscription/renew', [SubscriptionController::class, 'renewCheckout'])->name('subscription.renew');
    Route::post('/subscription/renew', [SubscriptionController::class, 'renew'])->name('subscription.renew.pay');

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

Route::middleware(['auth', EnsurePasswordChanged::class, EnsureLegalAccepted::class, 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/search', \App\Http\Controllers\Admin\SearchController::class)->name('search');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/send-reset', [UserController::class, 'sendResetLink'])->middleware('throttle:10,1')->name('users.send-reset');
    Route::post('/users/{user}/temporary-password', [UserController::class, 'temporaryPassword'])->middleware('throttle:10,1')->name('users.temporary-password');

    Route::get('/therapists', [TherapistApprovalController::class, 'index'])->name('therapists.index');
    Route::get('/therapists/create', [TherapistApprovalController::class, 'create'])->name('therapists.create');
    Route::post('/therapists', [TherapistApprovalController::class, 'store'])->name('therapists.store');
    Route::post('/therapists/{therapist}/approve', [TherapistApprovalController::class, 'approve'])->name('therapists.approve');
    Route::post('/therapists/{therapist}/reject', [TherapistApprovalController::class, 'reject'])->name('therapists.reject');
    // Licence changes requested from a therapist's own profile (2026-10-06).
    Route::post('/therapists/{therapist}/license/approve', [TherapistApprovalController::class, 'approveLicense'])->name('therapists.license.approve');
    Route::post('/therapists/{therapist}/license/reject', [TherapistApprovalController::class, 'rejectLicense'])->name('therapists.license.reject');
    // In-person sessions (2026-10-07).
    Route::post('/therapists/{therapist}/in-person', [TherapistApprovalController::class, 'toggleInPerson'])->name('therapists.in-person');

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
    Route::post('/session-routing/{therapySession}/location', [SessionRoutingController::class, 'location'])->name('session-routing.location');
    // Calendar (2026-10-08).
    Route::get('/calendar', [AdminCalendarController::class, 'index'])->name('calendar');
    Route::post('/sessions/{therapySession}/reschedule', [AdminCalendarController::class, 'reschedule'])->name('sessions.reschedule');
    Route::post('/sessions/{therapySession}/cancel', [AdminCalendarController::class, 'cancel'])->name('sessions.cancel');
    Route::get('/schedule-requests', [ScheduleRequestController::class, 'index'])->name('schedule-requests.index');
    Route::post('/schedule-requests/{changeRequest}/approve', [ScheduleRequestController::class, 'approve'])->name('schedule-requests.approve');
    Route::post('/schedule-requests/{changeRequest}/decline', [ScheduleRequestController::class, 'decline'])->name('schedule-requests.decline');
    Route::post('/time-off/{timeOff}/decide', [ScheduleRequestController::class, 'decideTimeOff'])->name('time-off.decide');

    Route::get('/closed-sessions', [ClosedSessionController::class, 'index'])->name('closed-sessions.index');
    Route::get('/closed-sessions/{therapySession}', [ClosedSessionController::class, 'show'])->name('closed-sessions.show');
    Route::get('/closed-sessions/{therapySession}/pdf', [ClosedSessionController::class, 'pdf'])->name('closed-sessions.pdf');

    Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
    Route::get('/facilities/create', [FacilityController::class, 'create'])->name('facilities.create');
    Route::post('/facilities', [FacilityController::class, 'store'])->name('facilities.store');
    Route::get('/facilities/{facility}/edit', [FacilityController::class, 'edit'])->name('facilities.edit');
    Route::put('/facilities/{facility}', [FacilityController::class, 'update'])->name('facilities.update');

    // Richer intake (2026-10-09, roadmap 63): admin switches the "About you" sections on/off.
    Route::get('/intake-settings', [\App\Http\Controllers\Admin\IntakeSettingsController::class, 'edit'])->name('intake-settings.edit');
    Route::put('/intake-settings', [\App\Http\Controllers\Admin\IntakeSettingsController::class, 'update'])->name('intake-settings.update');

    // In-person venues + vouchers (2026-10-07).
    Route::get('/venues', [VenueController::class, 'index'])->name('venues.index');
    Route::get('/venues/create', [VenueController::class, 'create'])->name('venues.create');
    Route::post('/venues', [VenueController::class, 'store'])->name('venues.store');
    Route::get('/venues/{venue}/edit', [VenueController::class, 'edit'])->name('venues.edit');
    Route::put('/venues/{venue}', [VenueController::class, 'update'])->name('venues.update');
    Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/create', [VoucherController::class, 'create'])->name('vouchers.create');
    Route::post('/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/{voucher}/edit', [VoucherController::class, 'edit'])->name('vouchers.edit');
    Route::put('/vouchers/{voucher}', [VoucherController::class, 'update'])->name('vouchers.update');
    Route::post('/vouchers/{voucher}/toggle', [VoucherController::class, 'toggle'])->name('vouchers.toggle');

    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::post('/referrals/{therapySession}/assign', [ReferralController::class, 'assign'])->name('referrals.assign');

    Route::get('/client-subscriptions', [ClientSubscriptionController::class, 'index'])->name('client-subscriptions.index');
    Route::post('/client-subscriptions', [ClientSubscriptionController::class, 'store'])->name('client-subscriptions.store');
    Route::post('/client-subscriptions/{subscription}/renew-cash', [ClientSubscriptionController::class, 'renewCash'])->name('client-subscriptions.renew-cash');

    // Payments & revenue (2026-10-02) — see Admin\PaymentController.
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/export', [AdminPaymentController::class, 'export'])->name('payments.export');
    Route::get('/payments/{transaction}/receipt', [AdminPaymentController::class, 'receipt'])->name('payments.receipt');
    Route::post('/payments/{transaction}/refund', [AdminPaymentController::class, 'refund'])->name('payments.refund');

    // A client's progress (2026-10-03) — same page therapists see, minus the mood chart.
    // Clients list + client file (2026-10-06) — same pages therapists see for their own clients.
    // Check-in overview (2026-10-06, roadmap 28).
    Route::get('/check-ins', [CheckInOverviewController::class, 'index'])->name('check-ins.index');
    Route::get('/clients', [AdminClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{clientProfile}', [AdminClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{clientProfile}/progress', [AdminClientProgressController::class, 'show'])->name('clients.progress');

    // Session ratings (2026-10-04) — see Admin\RatingController.
    Route::get('/ratings', [AdminRatingController::class, 'index'])->name('ratings.index');
});

Route::middleware(['auth', EnsurePasswordChanged::class, EnsureLegalAccepted::class, 'role:therapist'])->prefix('therapist')->name('therapist.')->group(function () {
    Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
    Route::post('/availability', [AvailabilityController::class, 'store'])->name('availability.store');
    Route::delete('/availability/{slot}', [AvailabilityController::class, 'destroy'])->name('availability.destroy');
    // Calendar (2026-10-08).
    Route::get('/calendar', [TherapistCalendarController::class, 'index'])->name('calendar');
    Route::post('/sessions/{therapySession}/change-request', [TherapistCalendarController::class, 'requestChange'])->middleware('throttle:10,1')->name('sessions.change-request');
    Route::post('/change-requests/{changeRequest}/withdraw', [TherapistCalendarController::class, 'withdraw'])->name('change-requests.withdraw');
    Route::post('/time-off', [TherapistCalendarController::class, 'storeTimeOff'])->name('time-off.store');
    Route::post('/time-off/{timeOff}/cancel', [TherapistCalendarController::class, 'cancelTimeOff'])->name('time-off.cancel');

    Route::get('/sessions', [TherapistSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/{therapySession}/close', [TherapistSessionController::class, 'close'])->name('sessions.close');
    Route::post('/sessions/{therapySession}/close', [TherapistSessionController::class, 'store'])->name('sessions.store');

    Route::get('/reports', [TherapistReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{therapySession}', [TherapistReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{therapySession}/pdf', [TherapistReportController::class, 'pdf'])->name('reports.pdf');

    // A client's progress since intake (2026-10-03) — see Therapist\ClientProgressController.
    // Therapist workspace (2026-10-06): My clients + client file, My stats, My profile.
    Route::get('/clients', [TherapistClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{clientProfile}', [TherapistClientController::class, 'show'])->name('clients.show');
    Route::get('/stats', [TherapistStatsController::class, 'index'])->name('stats');
    Route::get('/profile', [TherapistProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [TherapistProfileController::class, 'update'])->name('profile.update');
    Route::get('/clients/{clientProfile}/progress', [ClientProgressController::class, 'show'])->name('clients.progress');
});
