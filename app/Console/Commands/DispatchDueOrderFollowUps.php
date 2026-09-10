<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderFollowUp;
use App\Models\AppSetting;
use App\Models\OrderFollowUp;
use App\Support\ReminderSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchDueOrderFollowUps extends Command
{
    protected $signature = 'orders:dispatch-follow-ups';

    protected $description = 'Queue due order reminders and escalations';

    public function handle(): int
    {
        AppSetting::putString('order_reminders.last_scheduler_run', now()->toIso8601String());
        if (! ReminderSettings::enabled()) {
            $this->components->info('Order reminders are paused.');

            return self::SUCCESS;
        }

        $queued = 0;
        OrderFollowUp::query()
            ->where('status', OrderFollowUp::STATUS_PENDING)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($followUps) use (&$queued) {
                foreach ($followUps as $candidate) {
                    $claimed = DB::transaction(function () use ($candidate) {
                        $followUp = OrderFollowUp::query()->whereKey($candidate->id)->lockForUpdate()->first();
                        if (! $followUp || $followUp->status !== OrderFollowUp::STATUS_PENDING || $followUp->next_due_at?->isFuture()) {
                            return false;
                        }

                        $followUp->update(['status' => OrderFollowUp::STATUS_DISPATCHING]);

                        return true;
                    });

                    if ($claimed) {
                        try {
                            SendOrderFollowUp::dispatch($candidate->id);
                            $queued++;
                        } catch (\Throwable $exception) {
                            OrderFollowUp::query()->whereKey($candidate->id)->update([
                                'status' => OrderFollowUp::STATUS_PENDING,
                                'last_error_at' => now(),
                                'next_due_at' => now()->addHour(),
                            ]);
                            AppSetting::putString('order_reminders.last_failure', now()->toIso8601String().' A follow-up could not be queued.');
                            report($exception);
                        }
                    }
                }
            });

        $this->components->info("Queued {$queued} follow-up(s).");

        return self::SUCCESS;
    }
}
