<?php

namespace App\Jobs;

use App\Models\OrderFollowUp;
use App\Models\User;
use App\Services\OrderFollowUpDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderFollowUpSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 50;

    public function __construct(
        public readonly int $followUpId,
        public readonly int $recipientUserId,
        public readonly string $level,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(OrderFollowUpDispatcher $dispatcher): void
    {
        $followUp = OrderFollowUp::query()->find($this->followUpId);
        $recipient = User::query()->whereKey($this->recipientUserId)
            ->where('role', User::ROLE_CUSTOMER)->where('is_active', true)->first();

        if ($followUp && $recipient) {
            $dispatcher->sendDeferredSms($followUp, $recipient, $this->level);
        }
    }
}
