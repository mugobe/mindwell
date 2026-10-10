<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\MessageController as WebMessages;
use App\Http\Controllers\SessionRatingController;
use App\Http\Controllers\VideoRoomController;
use App\Http\Middleware\EnsureApiClient;
use App\Http\Middleware\EnsureLegalAccepted;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mindwell mobile API — version 1 (2026-10-09)
|--------------------------------------------------------------------------
| For the Android client app (clients only — Collins). Everything is under /api/v1 and
| speaks JSON. Sign in with POST /api/v1/auth/login → { token }, then send
| "Authorization: Bearer <token>" and "Accept: application/json" on every call.
|
| Business rules are NOT re-implemented here: these controllers call the same code the
| website uses (App\Services\Checkout, IntakeController::submit, bookSlot, finalize …),
| and a few website actions that already return JSON are routed to directly.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ---- Public --------------------------------------------------------------
    Route::get('config', [V1\ConfigController::class, 'show'])->name('config');

    Route::middleware('throttle:20,1,api-auth')->group(function () {
        Route::post('auth/register', [V1\AuthController::class, 'register'])->name('auth.register');
        Route::post('auth/login', [V1\AuthController::class, 'login'])->name('auth.login');
        Route::post('auth/forgot-password', [V1\AuthController::class, 'forgotPassword'])->name('auth.forgot');
    });

    // ---- Signed-in clients -----------------------------------------------------
    Route::middleware(['auth:sanctum', EnsureApiClient::class, EnsurePasswordChanged::class, EnsureLegalAccepted::class, 'throttle:120,1'])->group(function () {

        Route::post('auth/logout', [V1\AuthController::class, 'logout'])->name('auth.logout');

        // Me / settings
        Route::get('me', [V1\MeController::class, 'show'])->name('me');
        Route::put('me', [V1\MeController::class, 'update'])->name('me.update');
        Route::put('me/notifications', [V1\MeController::class, 'updateNotifications'])->name('me.notifications');
        Route::put('me/password', [V1\MeController::class, 'password'])->middleware('throttle:6,1,api-pw')->name('me.password');
        Route::post('consent', [V1\MeController::class, 'consent'])->name('consent');

        // Home
        Route::get('dashboard', [V1\DashboardController::class, 'show'])->name('dashboard');

        // Intake + About you
        Route::get('intake', [V1\IntakeController::class, 'show'])->name('intake.show');
        Route::post('intake', [V1\IntakeController::class, 'store'])->name('intake.store');
        Route::get('about-you', [V1\IntakeController::class, 'about'])->name('about.show');
        Route::put('about-you', [V1\IntakeController::class, 'updateAbout'])->name('about.update');
        Route::post('about-you/not-now', [V1\IntakeController::class, 'dismissAbout'])->name('about.dismiss');

        // Therapists + booking
        Route::get('therapists', [V1\TherapistController::class, 'index'])->name('therapists.index');
        Route::get('therapists/{therapist}', [V1\TherapistController::class, 'show'])->name('therapists.show');
        Route::post('therapists/{therapist}/book', [V1\TherapistController::class, 'book'])->name('therapists.book');

        // Sessions
        Route::get('sessions', [V1\SessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/{therapySession}', [V1\SessionController::class, 'show'])->name('sessions.show');
        Route::get('sessions/{therapySession}/lobby', [V1\SessionController::class, 'lobby'])->name('sessions.lobby');
        Route::post('sessions/{therapySession}/stop-waiting', [V1\SessionController::class, 'stopWaiting'])->name('sessions.stop-waiting');
        Route::get('sessions/{therapySession}/change', [V1\SessionController::class, 'changeOptions'])->name('sessions.change.options');
        Route::post('sessions/{therapySession}/change', [V1\SessionController::class, 'requestChange'])->name('sessions.change.store');
        Route::delete('change-requests/{changeRequest}', [V1\SessionController::class, 'withdrawChange'])->name('change-requests.withdraw');

        // Paying for a session
        Route::get('sessions/{therapySession}/checkout', [V1\CheckoutController::class, 'quote'])->name('sessions.checkout');
        Route::post('sessions/{therapySession}/pay', [V1\CheckoutController::class, 'pay'])->middleware('throttle:10,1,api-pay')->name('sessions.pay');

        // Video room (state here; token / presence / notepad are the website's JSON actions)
        Route::get('sessions/{therapySession}/room', [V1\SessionController::class, 'room'])->name('sessions.room');
        Route::post('sessions/{therapySession}/room/token', [VideoRoomController::class, 'token'])->name('sessions.room.token');
        Route::post('sessions/{therapySession}/room/presence', [VideoRoomController::class, 'presence'])->name('sessions.room.presence');
        Route::post('sessions/{therapySession}/room/notes', [VideoRoomController::class, 'notes'])->name('sessions.room.notes');

        // Rating (website action; answers JSON when Accept: application/json)
        Route::post('sessions/{therapySession}/rating', [SessionRatingController::class, 'store'])->name('sessions.rating');

        // Payments (any purpose) — follow until done, history, receipts
        Route::get('payments', [V1\PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{transaction}', [V1\CheckoutController::class, 'status'])->name('payments.status');
        Route::post('payments/{transaction}/simulate', [V1\CheckoutController::class, 'simulate'])->name('payments.simulate');
        Route::get('payments/{transaction}/receipt', [V1\PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::get('payments/{transaction}/receipt-link', [V1\PaymentController::class, 'receiptLink'])->name('payments.receipt-link');

        // My plan + employer benefit
        Route::get('plans', [V1\PlanController::class, 'index'])->name('plans.index');
        Route::get('plans/{plan}/checkout', [V1\PlanController::class, 'quote'])->name('plans.checkout');
        Route::post('plans/{plan}/subscribe', [V1\PlanController::class, 'subscribe'])->middleware('throttle:10,1,api-pay')->name('plans.subscribe');
        Route::get('subscription/renew', [V1\PlanController::class, 'renewQuote'])->name('subscription.renew.quote');
        Route::post('subscription/renew', [V1\PlanController::class, 'renew'])->middleware('throttle:10,1,api-pay')->name('subscription.renew');
        Route::get('employer-benefit', [V1\PlanController::class, 'employer'])->name('employer.show');
        Route::post('employer-benefit', [V1\PlanController::class, 'redeem'])->middleware('throttle:10,1,api-pay')->name('employer.redeem');

        // Messages
        Route::get('messages', [V1\MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/unread', [WebMessages::class, 'unread'])->name('messages.unread');
        Route::post('messages/with/{therapist}', [V1\MessageController::class, 'open'])->name('messages.open');
        Route::get('conversations/{conversation}/poll', [WebMessages::class, 'poll'])->name('messages.poll');
        Route::post('conversations/{conversation}/messages', [WebMessages::class, 'send'])->middleware('throttle:30,1,api-msg')->name('messages.send');

        // Journal
        Route::get('journal', [V1\JournalController::class, 'index'])->name('journal.index');
        Route::post('journal', [V1\JournalController::class, 'store'])->name('journal.store');
        Route::patch('journal/{entry}', [V1\JournalController::class, 'update'])->name('journal.update');
        Route::delete('journal/{entry}', [V1\JournalController::class, 'destroy'])->name('journal.destroy');

        // Progress + check-ins
        Route::get('progress', [V1\ProgressController::class, 'index'])->name('progress.index');
        Route::get('check-in', [V1\ProgressController::class, 'questions'])->name('check-in.show');
        Route::post('check-in', [V1\ProgressController::class, 'store'])->name('check-in.store');
        Route::put('progress/share-mood', [V1\ProgressController::class, 'shareMood'])->name('progress.share-mood');
    });
});
