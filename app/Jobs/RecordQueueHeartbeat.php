<?php

namespace App\Jobs;

use App\Services\ReliabilityHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public function handle(ReliabilityHealth $health): void
    {
        $health->recordQueueHeartbeat();
    }
}
