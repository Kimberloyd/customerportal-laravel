<?php

namespace Tests\Feature;

use App\Jobs\RecordQueueHeartbeat;
use App\Models\AppSetting;
use App\Services\ReliabilityHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['reliability.check_redis' => false]);
    }

    public function test_readiness_is_healthy_when_dependencies_and_heartbeats_are_current(): void
    {
        AppSetting::putString(ReliabilityHealth::SCHEDULER_HEARTBEAT, now()->toIso8601String());
        AppSetting::putString(ReliabilityHealth::QUEUE_HEARTBEAT, now()->toIso8601String());

        $this->getJson('/health/ready')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'database' => true,
                    'redis' => true,
                    'scheduler' => true,
                    'queue' => true,
                ],
            ])
            ->assertJsonStructure(['request_id']);
    }

    public function test_readiness_reports_stale_scheduler_and_queue_workers(): void
    {
        $stale = now()->subMinutes(10)->toIso8601String();
        AppSetting::putString(ReliabilityHealth::SCHEDULER_HEARTBEAT, $stale);
        AppSetting::putString(ReliabilityHealth::QUEUE_HEARTBEAT, $stale);

        $this->getJson('/health/ready')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => ['scheduler' => false, 'queue' => false],
            ]);
    }

    public function test_queue_heartbeat_job_records_worker_progress(): void
    {
        (new RecordQueueHeartbeat)->handle(app(ReliabilityHealth::class));

        $this->assertNotNull(AppSetting::string(ReliabilityHealth::QUEUE_HEARTBEAT));
    }
}
