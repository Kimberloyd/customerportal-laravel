<?php

namespace Tests\Feature\Messages;

use App\Models\CustomerMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class PublicConversationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function makeThreadWithToken(string $rawToken, array $overrides = []): CustomerMessage
    {
        $customer = $this->makeCustomer();

        return $this->makeThread($customer, array_merge([
            'public_token' => CustomerMessage::hashPublicToken($rawToken),
            'public_token_expires_at' => now()->addDay(),
        ], $overrides));
    }

    public function test_valid_token_works(): void
    {
        $token = Str::random(43);
        $thread = $this->makeThreadWithToken($token);

        $response = $this->get("/messages/customer/{$token}");

        $response->assertOk();
    }

    public function test_expired_token_404s(): void
    {
        $token = Str::random(43);
        $this->makeThreadWithToken($token, ['public_token_expires_at' => now()->subDay()]);

        $this->get("/messages/customer/{$token}")->assertStatus(404);
    }

    public function test_revoked_token_404s(): void
    {
        $token = Str::random(43);
        $this->makeThreadWithToken($token, ['public_token_revoked_at' => now()]);

        $this->get("/messages/customer/{$token}")->assertStatus(404);
    }

    public function test_wrong_channel_404s(): void
    {
        $token = Str::random(43);
        $this->makeThreadWithToken($token, ['channel' => 'facebook_messenger']);

        $this->get("/messages/customer/{$token}")->assertStatus(404);
    }

    public function test_wrong_token_404s(): void
    {
        $token = Str::random(43);
        $this->makeThreadWithToken($token);

        $this->get('/messages/customer/not-the-right-token')->assertStatus(404);
    }

    public function test_guest_can_reply(): void
    {
        $token = Str::random(43);
        $thread = $this->makeThreadWithToken($token);

        $response = $this->post("/messages/customer/{$token}", ['body' => 'A guest reply']);

        $response->assertRedirect(route('messages.customer-conversation', ['token' => $token]));
        $reply = CustomerMessage::where('parent_id', $thread->id)->first();
        $this->assertNotNull($reply);
        $this->assertSame('A guest reply', $reply->body);
        $this->assertSame('customer', $reply->sender_type);
    }

    public function test_reply_length_cap_enforced(): void
    {
        $token = Str::random(43);
        $thread = $this->makeThreadWithToken($token);
        $tooLong = str_repeat('a', 2001);

        $response = $this->post("/messages/customer/{$token}", ['body' => $tooLong]);

        $response->assertSessionHas('error', 'Keep your reply under 2000 characters, then send it again.');
        $this->assertSame(0, CustomerMessage::where('parent_id', $thread->id)->count());
    }

    public function test_closed_thread_rejects_guest_reply(): void
    {
        $token = Str::random(43);
        $thread = $this->makeThreadWithToken($token, ['status' => 'closed']);

        $response = $this->post("/messages/customer/{$token}", ['body' => 'A reply']);

        $response->assertSessionHas('error', 'This conversation is closed and no longer accepts replies.');
    }

    public function test_rate_limit_blocks_sixth_attempt(): void
    {
        $token = Str::random(43);
        $thread = $this->makeThreadWithToken($token);

        for ($i = 0; $i < 5; $i++) {
            $this->post("/messages/customer/{$token}", ['body' => "reply {$i}"])->assertRedirect();
        }

        $this->post("/messages/customer/{$token}", ['body' => 'one too many'])->assertStatus(429);
    }

    public function test_viewing_marks_company_messages_read_for_guest(): void
    {
        $token = Str::random(43);
        $thread = $this->makeThreadWithToken($token, ['sender_type' => 'company', 'is_read' => false]);

        $this->get("/messages/customer/{$token}");

        $this->assertTrue($thread->fresh()->is_read);
    }
}
