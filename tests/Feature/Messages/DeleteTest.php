<?php

namespace Tests\Feature\Messages;

use App\Models\CustomerMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_blocked_unless_closed(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $thread = $this->makeThread($this->makeCustomer(), ['status' => 'open']);

        $this->actingAsUser($staff)->post("/messages/{$thread->id}/delete");

        $this->assertNotNull(CustomerMessage::find($thread->id));
    }

    public function test_deletes_and_cascades_replies_when_closed(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $thread = $this->makeThread($customer, ['status' => 'closed']);
        $reply = $thread->replies()->create([
            'customer_id' => $customer->id,
            'subject' => $thread->subject,
            'body' => 'reply',
            'sender_type' => 'company',
            'is_read' => true,
            'status' => 'closed',
            'channel' => 'portal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAsUser($staff)->post("/messages/{$thread->id}/delete");

        $response->assertRedirect(route('messages.index', ['status' => 'all']));
        $this->assertNull(CustomerMessage::find($thread->id));
        $this->assertNull(CustomerMessage::find($reply->id));
    }

    public function test_customer_can_never_delete(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $thread = $this->makeThread($customer, ['status' => 'closed']);

        $this->actingAsUser($user)->post("/messages/{$thread->id}/delete")->assertStatus(403);
        $this->assertNotNull(CustomerMessage::find($thread->id));
    }
}
