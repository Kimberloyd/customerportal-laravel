<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PublicConversationController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Matches Flask's dashboard_bp, which handles both "/" and "/dashboard"
// with the same view (no separate marketing/welcome page in this app) --
// real visitors land on "/" and either see the dashboard or get bounced
// to /login by the 'auth' middleware on the dashboard route itself.
Route::redirect('/', '/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->prefix('orders')->name('purchase-orders.')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
    Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
    Route::get('/{order}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::get('/{order}/attachment', [PurchaseOrderController::class, 'attachment'])->name('attachment');
    Route::get('/{order}/print', [PurchaseOrderController::class, 'print'])->name('print');
    Route::get('/{order}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
    Route::put('/{order}', [PurchaseOrderController::class, 'update'])->name('update');
    Route::post('/{order}/complete', [PurchaseOrderController::class, 'complete'])->name('complete');
    Route::post('/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
    Route::post('/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
});

Route::middleware('auth')->prefix('customers')->name('customers.')->group(function () {
    Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
    Route::post('/{customer}/toggle-active', [CustomerController::class, 'toggleActive'])->name('toggle-active');
});

Route::get('/admin', [AdminDashboardController::class, 'index'])
    ->middleware('auth')
    ->name('admin.dashboard');

Route::middleware('auth')->prefix('admin/users')->name('admin.users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle-active');
});

Route::middleware('auth')->prefix('messages')->name('messages.')->group(function () {
    Route::get('/unread-count', [MessageController::class, 'unreadCount'])->name('unread-count');
    Route::get('/recipients', [MessageController::class, 'recipients'])->name('recipients');
    Route::get('/users-search', [MessageController::class, 'usersSearch'])->name('users-search');
    Route::get('/widget/facebook/{thread}', [MessageController::class, 'widgetFacebookShow'])->name('widget.facebook.show');
    Route::post('/widget/facebook/{thread}', [MessageController::class, 'widgetFacebookSend'])->name('widget.facebook.send');
    Route::post('/widget/facebook/{thread}/link', [MessageController::class, 'widgetFacebookLink'])->name('widget.facebook.link');
    Route::get('/widget/{customer}', [MessageController::class, 'widgetShow'])->name('widget.show');
    Route::post('/widget/{customer}', [MessageController::class, 'widgetSend'])->name('widget.send');
});

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/overview', [ReportController::class, 'overview'])->name('overview');
    Route::get('/orders', [ReportController::class, 'orders'])->name('orders');
    Route::get('/orders/export', [ReportController::class, 'exportOrders'])->name('orders.export');
});

// Unauthenticated guest conversation link -- token-gated, not session-based.
Route::get('/messages/customer/{token}', [PublicConversationController::class, 'show'])
    ->name('messages.customer-conversation');
Route::post('/messages/customer/{token}', [PublicConversationController::class, 'reply'])
    ->name('messages.customer-conversation.reply');

// Facebook webhook -- Meta calls this directly, no session/CSRF.
Route::get('/webhooks/facebook/messenger', [FacebookWebhookController::class, 'verify']);
Route::post('/webhooks/facebook/messenger', [FacebookWebhookController::class, 'receive']);

require __DIR__.'/auth.php';
