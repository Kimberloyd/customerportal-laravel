<?php

namespace Tests\Feature\Messages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_non_owning_customer_cannot_view_the_thread(): void
    {
        // The thread lookup is already scoped to the viewer's own
        // customer_id (rootThreadsQuery(), matching Flask's
        // root_threads_query()), so a thread belonging to someone else
        // is simply not found -- 404, not 403. authorizeThreadAccess()
        // exists as a defensive second guard for any future entry
        // point that doesn't pre-scope the query, but this route
        // always does, so it never actually reaches that check here
        // (same redundancy Flask's own require_thread_access() has in
        // message_view for this exact reason).
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);
        $other = $this->makeCustomer('Other Co');
        $thread = $this->makeThread($other);

        $this->actingAsUser($user)->get("/messages/{$thread->id}")->assertStatus(404);
    }

    public function test_owning_customer_can_view(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $thread = $this->makeThread($customer);

        $response = $this->actingAsUser($user)->get("/messages/{$thread->id}");

        $response->assertOk();
    }

    public function test_viewing_marks_the_other_sides_messages_read_not_your_own(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        // Root from customer (unread from staff's perspective).
        $thread = $this->makeThread($customer, ['sender_type' => 'customer', 'is_read' => false]);
        $reply = $thread->replies()->create([
            'customer_id' => $customer->id,
            'subject' => $thread->subject,
            'body' => 'staff reply',
            'sender_type' => 'company',
            'is_read' => false,
            'status' => 'open',
            'channel' => 'portal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsUser($staff)->get("/messages/{$thread->id}");

        // Staff's incoming side is sender_type=customer -- the root
        // should now be marked read; the company-sent reply (staff's
        // own outgoing message) should remain untouched (still unread,
        // since that's the customer's side to mark).
        $this->assertTrue($thread->fresh()->is_read);
        $this->assertFalse($reply->fresh()->is_read);
    }
}
