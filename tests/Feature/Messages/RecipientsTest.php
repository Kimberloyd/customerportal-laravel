<?php

namespace Tests\Feature\Messages;

use App\Http\Middleware\PreventRequestForgery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class RecipientsTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_recipients_response_carries_the_same_unread_count_as_the_dedicated_endpoint(): void
    {
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Acme Co', $customerUser);
        $staff = User::factory()->create(['role' => 'office']);
        // Sent by the company, unread by the customer -- what the
        // customer's own unread badge counts.
        $this->makeThread($customer, ['sender_type' => 'company', 'is_read' => false]);

        $count = $this->actingAsUser($customerUser)
            ->getJson(route('messages.unread-count'))
            ->json('count');

        $this->actingAsUser($customerUser)
            ->getJson(route('messages.recipients'))
            ->assertOk()
            ->assertJsonPath('unread_count', $count)
            ->assertJsonPath('unread_count', 1);
    }

    public function test_recipients_response_carries_unread_count_for_a_staff_viewer(): void
    {
        $customer = $this->makeCustomer('Acme Co');
        $staff = User::factory()->create(['role' => 'office']);
        // Sent by the customer, unread by staff -- what staff's badge counts.
        $this->makeThread($customer, ['sender_type' => 'customer', 'is_read' => false]);

        $count = $this->actingAsUser($staff)
            ->getJson(route('messages.unread-count'))
            ->json('count');

        $this->actingAsUser($staff)
            ->getJson(route('messages.recipients'))
            ->assertOk()
            ->assertJsonPath('unread_count', $count)
            ->assertJsonPath('unread_count', 1);
    }

    public function test_customer_with_no_linked_account_gets_zero_unread_count(): void
    {
        $orphanedUser = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($orphanedUser)
            ->getJson(route('messages.recipients'))
            ->assertOk()
            ->assertJson(['recipients' => [], 'unread_count' => 0]);
    }
}
