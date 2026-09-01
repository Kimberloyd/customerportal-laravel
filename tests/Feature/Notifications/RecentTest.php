<?php

namespace Tests\Feature\Notifications;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class RecentTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_customer_only_sees_their_own_orders_notifications(): void
    {
        $ownUser = User::factory()->create(['role' => 'customer']);
        $ownCustomer = $this->makeCustomer('Own Co', $ownUser);
        $otherCustomer = $this->makeCustomer('Other Co');
        $product = $this->makeProduct('Widget');

        $this->actingAsUser($ownUser)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $ownCustomer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $staff = User::factory()->create(['role' => 'employee']);
        $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $otherCustomer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $response = $this->actingAsUser($ownUser)->getJson(route('notifications.recent'));

        $response->assertOk();
        $this->assertSame(1, $response->json('count'));
        $this->assertCount(1, $response->json('notifications'));
        $this->assertSame(
            "Order received — we'll review it shortly.",
            $response->json('notifications.0.note'),
        );
    }

    public function test_staff_sees_notifications_across_all_customers(): void
    {
        $customerA = $this->makeCustomer('A Co');
        $customerB = $this->makeCustomer('B Co');
        $product = $this->makeProduct('Widget');
        $staff = User::factory()->create(['role' => 'employee']);

        foreach ([$customerA, $customerB] as $customer) {
            $this->actingAsUser($staff)->post('/orders', [
                'po_number' => 'PO-'.uniqid(),
                'customer_id' => $customer->id,
                'product_id' => [$product->id],
                'product_search' => [''],
                'quantity' => [1],
            ]);
        }

        $response = $this->actingAsUser($staff)->getJson(route('notifications.recent'));

        $response->assertOk();
        $this->assertSame(2, $response->json('count'));
        $messages = collect($response->json('notifications'))->pluck('note');
        $this->assertTrue($messages->contains('New order from A Co — ready for review.'));
        $this->assertTrue($messages->contains('New order from B Co — ready for review.'));
    }

    public function test_customer_and_staff_receive_copy_written_for_their_roles(): void
    {
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Customer Hospital', $customerUser);
        $staff = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct('Widget');

        $this->actingAsUser($customerUser)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $customerMessage = $this->actingAsUser($customerUser)
            ->getJson(route('notifications.recent'))
            ->json('notifications.0.note');

        $staffMessage = $this->actingAsUser($staff)
            ->getJson(route('notifications.recent'))
            ->json('notifications.0.note');

        $this->assertSame("Order received — we'll review it shortly.", $customerMessage);
        $this->assertSame('New order from Customer Hospital — ready for review.', $staffMessage);
        $this->assertNotSame($customerMessage, $staffMessage);
    }

    public function test_a_fresh_order_event_shows_up_in_the_feed(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);

        $this->actingAsUser($staff)->post("/orders/{$order->id}/complete");

        $response = $this->actingAsUser($staff)->getJson(route('notifications.recent'));

        $response->assertOk();
        $notifications = collect($response->json('notifications'));
        $this->assertTrue($notifications->contains(
            fn ($n) => $n['order_id'] === $order->id
                && $n['note'] === 'Order for Acme Co is complete.',
        ));
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->getJson(route('notifications.recent'))->assertUnauthorized();
    }

    public function test_mark_all_read_zeroes_the_count(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $before = $this->actingAsUser($staff)->getJson(route('notifications.recent'));
        $this->assertSame(1, $before->json('count'));

        $this->actingAsUser($staff)
            ->postJson(route('notifications.mark-all-read'))
            ->assertOk()
            ->assertJson(['count' => 0]);

        $after = $this->actingAsUser($staff)->getJson(route('notifications.recent'));
        $this->assertSame(0, $after->json('count'));

        // The list itself is unaffected by "read" state -- only the badge count is.
        $this->assertCount(1, $after->json('notifications'));
    }

    public function test_notifications_created_after_marking_read_still_count(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAsUser($staff)
            ->postJson(route('notifications.mark-all-read'))
            ->assertOk();

        // created_at/notifications_read_at are second-precision columns --
        // travel forward so the new notification lands in a distinct
        // second from the mark-read timestamp, exactly as it would in
        // real usage (nobody reads their notifications and has a new
        // order land in the same wall-clock second).
        $this->travel(1)->second();

        $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $response = $this->actingAsUser($staff)->getJson(route('notifications.recent'));
        $this->assertSame(1, $response->json('count'));
    }
}
