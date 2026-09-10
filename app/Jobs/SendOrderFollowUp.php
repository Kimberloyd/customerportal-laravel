<?php

namespace App\Jobs;

use App\Models\OrderFollowUp;
use App\Services\OrderFollowUpDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendOrderFollowUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 50;

    public function __construct(public readonly int $followUpId)
    {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(OrderFollowUpDispatcher $dispatcher): void
    {
        $followUp = OrderFollowUp::query()->find($this->followUpId);
        if (! $followUp
            || ! in_array($followUp->status, [OrderFollowUp::STATUS_PENDING, OrderFollowUp::STATUS_DISPATCHING], true)
            || $followUp->next_due_at?->isFuture()) {
            return;
        }

        $dispatcher->dispatch($followUp);
    }

    public function failed(Throwable $exception): void
    {
        OrderFollowUp::query()->whereKey($this->followUpId)
            ->where('status', OrderFollowUp::STATUS_DISPATCHING)
            ->update([
                'status' => OrderFollowUp::STATUS_PENDING,
                'last_error_at' => now(),
                'next_due_at' => now()->addHour(),
            ]);
    }
}
