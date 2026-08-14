<?php

namespace Tests\Feature\Messages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class FacebookLinkingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_linking_displaces_other_thread_already_linked_to_that_customer(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $alreadyLinked = $this->makeThread($customer, ['channel' => 'facebook_messenger']);
        $newThread = $this->makeThread(null, ['channel' => 'facebook_messenger']);

        $this->actingAsUser($staff)->post("/messages/{$newThread->id}/customer", ['customer_id' => $customer->id]);

        $this->assertSame($customer->id, $newThread->fresh()->customer_id);
        $this->assertNull($alreadyLinked->fresh()->customer_id);
    }

    public function test_sender_name_override_sets_and_clears(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $thread = $this->makeThread(null, ['channel' => 'facebook_messenger']);

        $this->actingAsUser($staff)->post("/messages/{$thread->id}/sender-name", ['sender_name' => 'Jane Guest']);
        $this->assertSame('Jane Guest', $thread->fresh()->external_sender_name);

        $this->actingAsUser($staff)->post("/messages/{$thread->id}/sender-name", ['sender_name' => '']);
        $this->assertNull($thread->fresh()->external_sender_name);
    }

    public function test_customer_role_gets_403_on_both_actions(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $thread = $this->makeThread($customer, ['channel' => 'facebook_messenger']);

        $this->actingAsUser($user)->post("/messages/{$thread->id}/customer", ['customer_id' => $customer->id])->assertStatus(403);
        $this->actingAsUser($user)->post("/messages/{$thread->id}/sender-name", ['sender_name' => 'x'])->assertStatus(403);
    }

    public function test_non_facebook_thread_404s_on_both_actions(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $thread = $this->makeThread($customer, ['channel' => 'portal']);

        $this->actingAsUser($staff)->post("/messages/{$thread->id}/customer", ['customer_id' => $customer->id])->assertStatus(404);
        $this->actingAsUser($staff)->post("/messages/{$thread->id}/sender-name", ['sender_name' => 'x'])->assertStatus(404);
    }

    public function test_public_link_rotate_and_revoke_are_non_facebook_only(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $portalThread = $this->makeThread($customer, ['channel' => 'portal']);
        $fbThread = $this->makeThread($customer, ['channel' => 'facebook_messenger']);

        $this->actingAsUser($staff)->post("/messages/{$portalThread->id}/public-link", ['action' => 'rotate'])
            ->assertRedirect(route('messages.show', $portalThread->id));
        $this->assertNotNull($portalThread->fresh()->public_token);

        $this->actingAsUser($staff)->post("/messages/{$fbThread->id}/public-link", ['action' => 'rotate'])
            ->assertStatus(404);
    }

    public function test_public_link_revoke_sets_revoked_at(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $thread = $this->makeThread($this->makeCustomer(), ['channel' => 'portal']);

        $this->actingAsUser($staff)->post("/messages/{$thread->id}/public-link", ['action' => 'revoke']);

        $this->assertNotNull($thread->fresh()->public_token_revoked_at);
    }
}
