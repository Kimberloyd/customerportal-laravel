<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\OrderAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_owning_customer_can_view_their_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $response = $this->actingAsUser($user)->get("/orders/{$order->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/Show')
            ->where('order.po_number', $order->po_number)
            ->where('isCustomerViewer', true)
        );
    }

    public function test_non_owning_customer_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);
        $otherCustomer = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();
        $order = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($user)->get("/orders/{$order->id}")->assertStatus(403);
    }

    public function test_staff_can_view_any_order(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get("/orders/{$order->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('isCustomerViewer', false));
    }

    public function test_actor_column_hidden_for_customer_viewer_but_present_for_staff(): void
    {
        $staff = User::factory()->create(['role' => 'employee', 'full_name' => 'Jane Staff']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->actingAsUser($staff);
        OrderAudit::record($order, 'Order Created', 'Created with 1 product line(s).', Request::create('/'));

        $staffResponse = $this->actingAsUser($staff)->get("/orders/{$order->id}");
        $staffResponse->assertInertia(fn ($page) => $page->where('order.audit_logs.0.actor_name', 'Jane Staff'));

        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer->update(['user_id' => $customerUser->id]);
        $customerResponse = $this->actingAsUser($customerUser)->get("/orders/{$order->id}");
        $customerResponse->assertInertia(fn ($page) => $page->where('order.audit_logs.0.actor_name', null));
    }

    public function test_attachment_route_enforces_same_access_check(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);
        $otherCustomer = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();
        $order = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($user)->get("/orders/{$order->id}/attachment")->assertStatus(403);
    }

    public function test_attachment_route_404s_when_no_file(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($staff)->get("/orders/{$order->id}/attachment")->assertStatus(404);
    }
}
