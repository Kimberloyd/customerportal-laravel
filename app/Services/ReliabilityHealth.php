<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ReliabilityHealth
{
    public const SCHEDULER_HEARTBEAT = 'health.scheduler_at';

    public const QUEUE_HEARTBEAT = 'health.queue_at';

    public function status(): array
    {
        $checks = [
            'database' => $this->canConnectToDatabase(),
            'redis' => $this->canConnectToRedis(),
            'scheduler' => $this->storedHeartbeatIsFresh(self::SCHEDULER_HEARTBEAT),
            'queue' => $this->storedHeartbeatIsFresh(self::QUEUE_HEARTBEAT),
        ];

        return [
            'status' => in_array(false, $checks, true) ? 'degraded' : 'ok',
            'checks' => $checks,
        ];
    }

    public function recordSchedulerHeartbeat(): void
    {
        AppSetting::putString(self::SCHEDULER_HEARTBEAT, now()->toIso8601String());
    }

    public function recordQueueHeartbeat(): void
    {
        AppSetting::putString(self::QUEUE_HEARTBEAT, now()->toIso8601String());
    }

    private function canConnectToDatabase(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function canConnectToRedis(): bool
    {
        if (! config('reliability.check_redis')) {
            return true;
        }

        try {
            $response = Redis::connection()->ping();

            return $response === true || strtoupper((string) $response) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    private function heartbeatIsFresh(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        try {
            return Carbon::parse($value)->gte(
                now()->subSeconds(config('reliability.heartbeat_max_age_seconds')),
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function storedHeartbeatIsFresh(string $key): bool
    {
        try {
            return $this->heartbeatIsFresh(AppSetting::string($key));
        } catch (Throwable) {
            return false;
        }
    }
}
