<?php

namespace Tests\Feature\Messages;

use App\Models\CustomerMessage;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class MarkAllReadTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_staff_marks_all_customer_messages_read(): void
    {
        $customer = $this->makeCustomer();
        $threadA = $this->makeThread($customer, ['sender_type' => 'customer', 'is_read' => false]);
        $threadB = $this->makeThread($customer, ['sender_type' => 'customer', 'is_read' => false]);
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)
            ->postJson(route('messages.mark-all-read'))
            ->assertOk()
            ->assertJson(['count' => 0]);

        $this->assertTrue($threadA->fresh()->is_read);
        $this->assertTrue($threadB->fresh()->is_read);
        $this->assertSame(0, $this->actingAsUser($staff)->getJson(route('messages.unread-count'))->json('count'));
    }

    public function test_customer_marking_read_does_not_touch_other_customers_messages(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $ownCustomer = $this->makeCustomer('Own Co', $user);
        $otherCustomer = $this->makeCustomer('Other Co');

        $ownThread = $this->makeThread($ownCustomer, ['sender_type' => 'company', 'is_read' => false]);
        $otherThread = $this->makeThread($otherCustomer, ['sender_type' => 'company', 'is_read' => false]);

        $this->actingAsUser($user)
            ->postJson(route('messages.mark-all-read'))
            ->assertOk();

        $this->assertTrue($ownThread->fresh()->is_read);
        $this->assertFalse($otherThread->fresh()->is_read);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson(route('messages.mark-all-read'))->assertUnauthorized();
    }
}
