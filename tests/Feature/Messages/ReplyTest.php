<?php

namespace Tests\Feature\Messages;

use App\Models\CustomerMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ReplyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_empty_body_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $thread = $this->makeThread($this->makeCustomer());

        $response = $this->actingAsUser($staff)->post("/messages/{$thread->id}/reply", ['body' => '']);

        $response->assertSessionHas('error', 'Enter a reply before sending.');
        $this->assertSame(0, CustomerMessage::where('parent_id', $thread->id)->count());
    }

    public function test_closed_thread_rejects_reply(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $thread = $this->makeThread($this->makeCustomer(), ['status' => 'closed']);

        $response = $this->actingAsUser($staff)->post("/messages/{$thread->id}/reply", ['body' => 'hi']);

        $response->assertSessionHas('error', 'Reopen this conversation before replying.');
        $this->assertSame(0, CustomerMessage::where('parent_id', $thread->id)->count());
    }

    public function test_reply_reopens_thread_and_creates_row(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $thread = $this->makeThread($this->makeCustomer());

        $this->actingAsUser($staff)->post("/messages/{$thread->id}/reply", ['body' => 'A reply']);

        $reply = CustomerMessage::where('parent_id', $thread->id)->first();
        $this->assertNotNull($reply);
        $this->assertSame('A reply', $reply->body);
        $this->assertSame('company', $reply->sender_type);
        $this->assertSame('open', $thread->fresh()->status);
    }

    public function test_customer_reply_uses_customer_sender_type(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $thread = $this->makeThread($customer);

        $this->actingAsUser($user)->post("/messages/{$thread->id}/reply", ['body' => 'A reply']);

        $reply = CustomerMessage::where('parent_id', $thread->id)->first();
        $this->assertSame('customer', $reply->sender_type);
    }

    public function test_facebook_thread_reply_is_staff_only(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $thread = $this->makeThread($customer, ['channel' => 'facebook_messenger']);

        $this->actingAsUser($user)->post("/messages/{$thread->id}/reply", ['body' => 'A reply'])->assertStatus(403);
    }

    public function test_facebook_thread_reply_uses_stubbed_send(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $thread = $this->makeThread($customer, ['channel' => 'facebook_messenger']);

        // No Messenger credentials configured -- the stub returns null
        // (not an exception), and the reply is still persisted locally.
        $response = $this->actingAsUser($staff)->post("/messages/{$thread->id}/reply", ['body' => 'A reply']);

        $response->assertRedirect(route('messages.show', $thread->id));
        $reply = CustomerMessage::where('parent_id', $thread->id)->first();
        $this->assertNotNull($reply);
        $this->assertNull($reply->external_message_id);
    }
}
