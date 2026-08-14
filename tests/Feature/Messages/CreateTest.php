<?php

namespace Tests\Feature\Messages;

use App\Models\CustomerMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_is_forced_to_their_own_account(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $own = $this->makeCustomer('Own Co', $user);
        $other = $this->makeCustomer('Other Co');

        $this->actingAsUser($user)->post('/messages', [
            'customer_id' => $other->id,
            'subject' => 'Hello',
            'body' => 'Test message',
        ]);

        $thread = CustomerMessage::first();
        $this->assertSame($own->id, $thread->customer_id);
    }

    public function test_staff_recipient_list_excludes_customers_without_active_linked_user(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $linkedActiveUser = User::factory()->create(['role' => 'customer']);
        $eligible = $this->makeCustomer('Eligible Co', $linkedActiveUser);
        $unlinked = $this->makeCustomer('Unlinked Co');

        $response = $this->actingAsUser($staff)->get('/messages/new');

        $response->assertInertia(fn ($page) => $page->has('recipients', 1));
    }

    public function test_staff_cannot_message_a_customer_not_in_recipient_list(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $unlinked = $this->makeCustomer('Unlinked Co');

        $response = $this->actingAsUser($staff)->post('/messages', [
            'customer_id' => $unlinked->id,
            'subject' => 'Hello',
            'body' => 'Test message',
        ]);

        $response->assertSessionHasErrors('customer_id');
        $this->assertSame(0, CustomerMessage::count());
    }

    public function test_subject_and_body_required(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);

        $response = $this->actingAsUser($user)->post('/messages', [
            'subject' => '',
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertSame(0, CustomerMessage::count());
    }

    public function test_public_token_generated_on_new_thread(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);

        $this->actingAsUser($user)->post('/messages', [
            'subject' => 'Hello',
            'body' => 'Test message',
        ]);

        $thread = CustomerMessage::first();
        $this->assertNotNull($thread->public_token);
        $this->assertTrue($thread->public_token_expires_at->isFuture());
        $this->assertTrue($thread->publicLinkIsActive());
    }
}
