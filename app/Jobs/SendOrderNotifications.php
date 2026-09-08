<?php

namespace App\Jobs;

use App\Models\PurchaseOrder;
use App\Support\OrderNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    public function __construct(
        public readonly int $orderId,
        public readonly string $event,
        public readonly ?string $context = null,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $order = PurchaseOrder::withTrashed()->find($this->orderId);

        if ($order) {
            OrderNotifications::deliver($order, $this->event, $this->context);
        }
    }
}
