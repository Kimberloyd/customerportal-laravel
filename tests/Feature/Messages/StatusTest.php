<?php

namespace Tests\Feature\Messages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_staff_can_close_and_reopen(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $thread = $this->makeThread($this->makeCustomer());

        $this->actingAsUser($staff)->post("/messages/{$thread->id}/status", ['status' => 'closed']);
        $this->assertSame('closed', $thread->fresh()->status);

        $this->actingAsUser($staff)->post("/messages/{$thread->id}/status", ['status' => 'open']);
        $this->assertSame('open', $thread->fresh()->status);
    }

    public function test_customer_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $thread = $this->makeThread($customer);

        $this->actingAsUser($user)->post("/messages/{$thread->id}/status", ['status' => 'closed'])->assertStatus(403);
    }
}
