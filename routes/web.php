<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicConversationController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
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
    Route::get('/{order}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
    Route::put('/{order}', [PurchaseOrderController::class, 'update'])->name('update');
    Route::post('/{order}/complete', [PurchaseOrderController::class, 'complete'])->name('complete');
    Route::post('/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
    Route::post('/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
});

Route::middleware('auth')->prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/create', [ProductController::class, 'create'])->name('create');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
    Route::post('/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('toggle-active');
});

Route::middleware('auth')->prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::get('/create', [CustomerController::class, 'create'])->name('create');
    Route::post('/', [CustomerController::class, 'store'])->name('store');
    Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
    Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
    Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
    Route::post('/{customer}/toggle-active', [CustomerController::class, 'toggleActive'])->name('toggle-active');
});

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
    Route::get('/', [MessageController::class, 'index'])->name('index');
    Route::get('/new', [MessageController::class, 'create'])->name('create');
    Route::post('/', [MessageController::class, 'store'])->name('store');
    Route::get('/unread-count', [MessageController::class, 'unreadCount'])->name('unread-count');
    Route::get('/{thread}', [MessageController::class, 'show'])->name('show');
    Route::post('/{thread}/reply', [MessageController::class, 'reply'])->name('reply');
    Route::post('/{thread}/status', [MessageController::class, 'status'])->name('status');
    Route::post('/{thread}/delete', [MessageController::class, 'destroy'])->name('destroy');
    Route::post('/{thread}/customer', [MessageController::class, 'customerLink'])->name('customer-link');
    Route::post('/{thread}/sender-name', [MessageController::class, 'senderName'])->name('sender-name');
    Route::post('/{thread}/public-link', [MessageController::class, 'publicLink'])->name('public-link');
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
