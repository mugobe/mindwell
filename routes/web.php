<?php

use App\Http\Controllers\Admin\TherapistApprovalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/therapists', [TherapistApprovalController::class, 'index'])->name('therapists.index');
    Route::post('/therapists/{therapist}/approve', [TherapistApprovalController::class, 'approve'])->name('therapists.approve');
    Route::post('/therapists/{therapist}/reject', [TherapistApprovalController::class, 'reject'])->name('therapists.reject');
});