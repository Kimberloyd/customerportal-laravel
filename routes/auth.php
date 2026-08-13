<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

// Matches app/auth/auth_routes.py: this is an admin-provisioned B2B
// portal with no public self-registration, no email-based password
// reset, and no email verification. Credentials are created/reset by
// staff (see App\Http\Controllers\Admin), never by the account holder.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::post('logout-all', [AuthenticatedSessionController::class, 'destroyAll'])
        ->name('logout.all');
});
