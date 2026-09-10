<?php

use App\Jobs\RecordQueueHeartbeat;
use App\Services\AccountDeletionService;
use App\Services\ReliabilityHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('customers:sync')->hourly();

Artisan::command('accounts:purge-deleted', function () {
    $purged = app(AccountDeletionService::class)->purgeDue();
    $this->info("Permanently erased {$purged} account(s).");
})->purpose('Permanently erase accounts whose retention period has ended');

Schedule::command('accounts:purge-deleted')->dailyAt('02:00')->withoutOverlapping();

Schedule::call(function () {
    app(ReliabilityHealth::class)->recordSchedulerHeartbeat();
    RecordQueueHeartbeat::dispatch()->onQueue('monitoring');
})->everyMinute()->name('reliability-heartbeats')->withoutOverlapping();

Schedule::command('orders:dispatch-follow-ups')
    ->everyFiveMinutes()
    ->name('order-follow-ups')
    ->withoutOverlapping()
    ->onOneServer();
