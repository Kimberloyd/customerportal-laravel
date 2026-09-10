<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AgentCustomerAccountController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductReturnController;
use App\Http\Controllers\PublicConversationController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TermsAndPrivacyController;
use Illuminate\Support\Facades\Route;

// Matches Flask's dashboard_bp, which handles both "/" and "/dashboard"
// with the same view (no separate marketing/welcome page in this app) --
// real visitors land on "/" and either see the dashboard or get bounced
// to /login by the 'auth' middleware on the dashboard route itself.
Route::redirect('/', '/dashboard');

Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::get('/faq', [FaqController::class, 'index'])
    ->middleware('auth')
    ->name('faq');

Route::get('/terms-and-privacy', [TermsAndPrivacyController::class, 'index'])
    ->name('terms-and-privacy');

Route::middleware('auth')->prefix('orders')->name('purchase-orders.')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
    Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('throttle:order-writes')->name('store');
    Route::get('/{order}/message-log', [PurchaseOrderController::class, 'messageLog'])->name('message-log');
    Route::get('/{order}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::get('/{order}/attachment', [PurchaseOrderController::class, 'attachment'])->middleware('throttle:private-downloads')->name('attachment');
    Route::get('/{order}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
    Route::put('/{order}', [PurchaseOrderController::class, 'update'])->middleware('throttle:order-writes')->name('update');
    Route::delete('/{order}', [PurchaseOrderController::class, 'destroy'])->middleware('throttle:admin-sensitive')->name('destroy');
    Route::post('/{order}/complete', [PurchaseOrderController::class, 'complete'])->middleware('throttle:order-writes')->name('complete');
    Route::post('/{order}/receive', [PurchaseOrderController::class, 'receive'])->middleware('throttle:order-writes')->name('receive');
    Route::post('/{order}/confirm-received', [PurchaseOrderController::class, 'confirmReceived'])
        ->middleware('throttle:order-writes')->name('confirm-received');
    Route::post('/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('throttle:order-writes')->name('cancel');
    Route::post('/{order}/returns', [ProductReturnController::class, 'store'])->middleware('throttle:return-actions')->name('returns.store');
    Route::get('/{order}/returns/{return}/attachments/{attachment}', [ProductReturnController::class, 'attachment'])
        ->whereNumber('attachment')
        ->middleware('throttle:private-downloads')
        ->name('returns.attachment');
    Route::put('/returns/{return}', [ProductReturnController::class, 'update'])->middleware('throttle:return-actions')->name('returns.update');
});

Route::middleware('auth')->prefix('customers')->name('customers.')->group(function () {
    Route::delete('/{customer}', [CustomerController::class, 'destroy'])->middleware('throttle:admin-sensitive')->name('destroy');
    Route::post('/{customer}/toggle-active', [CustomerController::class, 'toggleActive'])->middleware('throttle:admin-sensitive')->name('toggle-active');
});

Route::get('/admin', [AdminDashboardController::class, 'index'])
    ->middleware('auth')
    ->name('admin.dashboard');

Route::middleware('auth')->prefix('admin/teams')->name('admin.teams.')->group(function () {
    Route::post('/', [TeamController::class, 'store'])->middleware('throttle:admin-sensitive')->name('store');
    Route::put('/{team}', [TeamController::class, 'update'])->middleware('throttle:admin-sensitive')->name('update');
    Route::delete('/{team}', [TeamController::class, 'destroy'])->middleware('throttle:admin-sensitive')->name('destroy');
});

Route::middleware('auth')->prefix('customer-accounts')->name('customer-accounts.')->group(function () {
    Route::get('/create', [AgentCustomerAccountController::class, 'create'])->name('create');
    Route::post('/', [AgentCustomerAccountController::class, 'store'])->middleware('throttle:admin-sensitive')->name('store');
});

Route::middleware('auth')->prefix('admin/users')->name('admin.users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->middleware('throttle:admin-sensitive')->name('store');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('throttle:admin-sensitive')->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('throttle:admin-sensitive')->name('destroy');
    Route::post('/{user}/restore', [UserController::class, 'restore'])->middleware('throttle:admin-sensitive')->name('restore');
    Route::get('/{user}/data-export', [UserController::class, 'exportData'])
        ->middleware('throttle:report-exports')
        ->name('data-export');
    Route::delete('/{user}/erase-now', [UserController::class, 'eraseNow'])
        ->middleware('throttle:admin-sensitive')
        ->name('erase-now');
    Route::post('/{user}/toggle-active', [UserController::class, 'toggleActive'])->middleware('throttle:admin-sensitive')->name('toggle-active');
    Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->middleware('throttle:admin-sensitive')
        ->name('reset-password');
});

Route::middleware('auth')->prefix('messages')->name('messages.')->group(function () {
    Route::get('/unread-count', [MessageController::class, 'unreadCount'])->name('unread-count');
    Route::post('/mark-all-read', [MessageController::class, 'markAllRead'])->name('mark-all-read');
    Route::get('/recipients', [MessageController::class, 'recipients'])->name('recipients');
    Route::get('/users-search', [MessageController::class, 'usersSearch'])->name('users-search');
    Route::get('/widget/facebook/{thread}', [MessageController::class, 'widgetFacebookShow'])->name('widget.facebook.show');
    Route::post('/widget/facebook/{thread}', [MessageController::class, 'widgetFacebookSend'])->middleware('throttle:message-writes')->name('widget.facebook.send');
    Route::post('/widget/facebook/{thread}/link', [MessageController::class, 'widgetFacebookLink'])->middleware('throttle:message-writes')->name('widget.facebook.link');
    Route::patch('/widget/facebook/{thread}/rename', [MessageController::class, 'widgetFacebookRename'])->middleware('throttle:message-writes')->name('widget.facebook.rename');
    Route::get('/widget/{customer}', [MessageController::class, 'widgetShow'])->name('widget.show');
    Route::post('/widget/{customer}', [MessageController::class, 'widgetSend'])->middleware('throttle:message-writes')->name('widget.send');
});

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/recent', [NotificationController::class, 'recent'])->name('recent');
    Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
});

Route::middleware('auth')->prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'edit'])->name('edit');
    Route::put('/', [SettingsController::class, 'update'])->name('update');
    // Admin-only; the role check lives in the controller alongside the other
    // role gates in this app rather than in a dedicated middleware.
    Route::put('/sms', [SettingsController::class, 'updateSms'])->name('sms.update');
    Route::put('/reminders', [SettingsController::class, 'updateReminders'])
        ->middleware('throttle:admin-sensitive')->name('reminders.update');
    Route::post('/two-factor/setup', [TwoFactorAuthenticationController::class, 'beginSetup'])->middleware('throttle:two-factor')->name('two-factor.setup');
    Route::post('/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirmSetup'])->middleware('throttle:two-factor')->name('two-factor.confirm');
    Route::delete('/two-factor/setup', [TwoFactorAuthenticationController::class, 'cancelSetup'])->name('two-factor.cancel');
    Route::post('/two-factor/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->middleware('throttle:two-factor')->name('two-factor.recovery-codes');
    Route::delete('/two-factor', [TwoFactorAuthenticationController::class, 'disable'])->middleware('throttle:two-factor')->name('two-factor.disable');
});

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/overview', [ReportController::class, 'overview'])->name('overview');
    Route::get('/orders', [ReportController::class, 'orders'])->name('orders');
    Route::get('/orders/export', [ReportController::class, 'exportOrders'])->middleware('throttle:report-exports')->name('orders.export');
});

// Unauthenticated guest conversation link -- token-gated, not session-based.
Route::get('/messages/customer/{token}', [PublicConversationController::class, 'show'])
    ->name('messages.customer-conversation');
Route::post('/messages/customer/{token}', [PublicConversationController::class, 'reply'])
    ->name('messages.customer-conversation.reply');

// Facebook webhook -- Meta calls this directly, no session/CSRF.
Route::get('/webhooks/facebook/messenger', [FacebookWebhookController::class, 'verify']);
Route::post('/webhooks/facebook/messenger', [FacebookWebhookController::class, 'receive'])->middleware('throttle:webhooks');

require __DIR__.'/auth.php';
