<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->prefix('purchase-orders')->name('purchase-orders.')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
    Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
    Route::get('/{order}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::get('/{order}/attachment', [PurchaseOrderController::class, 'attachment'])->name('attachment');
});

require __DIR__.'/auth.php';
