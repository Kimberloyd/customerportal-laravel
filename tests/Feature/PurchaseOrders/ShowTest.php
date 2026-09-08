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
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_owning_customer_can_view_their_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct('Brand product', [
            'generic_name' => 'Generic product',
            'dosage' => '500mg',
        ]);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $response = $this->actingAsUser($user)->get("/orders/{$order->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/Show')
            ->where('order.po_number', $order->po_number)
            ->where('order.customer_id', $customer->id)
            ->where('order.items.0.product_name', $product->product_name)
            ->where('order.items.0.generic_name', 'Generic product')
            ->where('order.items.0.dosage', '500mg')
            ->where('editOrderCustomers.0.id', $customer->id)
            ->where('lockedCustomerId', $customer->id)
            ->missing('editOrderProducts')
            ->where('isCustomerViewer', true)
        );
    }

    public function test_edit_modal_loads_the_product_catalog_on_demand(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct('Searchable product');
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $response = $this->actingAsUser($user)->get("/orders/{$order->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
            'X-Inertia-Partial-Component' => 'PurchaseOrders/Show',
            'X-Inertia-Partial-Data' => 'editOrderProducts',
        ]);

        $response->assertOk()
            ->assertJsonPath('component', 'PurchaseOrders/Show')
            ->assertJsonCount(1, 'props.editOrderProducts')
            ->assertJsonPath('props.editOrderProducts.0.product_name', 'Searchable product');
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
        $staff = User::factory()->create(['role' => 'office']);
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
        $staff = User::factory()->create(['role' => 'office', 'full_name' => 'Jane Staff']);
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
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($staff)->get("/orders/{$order->id}/attachment")->assertStatus(404);
    }

    public function test_opening_a_submitted_order_is_read_only(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get("/orders/{$order->id}");

        $response->assertInertia(fn ($page) => $page
            ->where('order.status', PurchaseOrder::STATUS_SUBMITTED)
            ->where('canManageFulfillment', true)
            ->where('canComplete', false));
        $this->assertSame(PurchaseOrder::STATUS_SUBMITTED, $order->fresh()->status);
    }

    public function test_customer_opening_their_own_order_does_not_change_its_status(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($user)->get("/orders/{$order->id}");

        $this->assertSame(PurchaseOrder::STATUS_SUBMITTED, $order->fresh()->status);
    }

    public function test_opening_an_order_already_past_submitted_does_not_change_its_status(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $product->id, 'quantity' => 5, 'delivered_quantity' => 2],
        ]);

        $response = $this->actingAsUser($staff)->get("/orders/{$order->id}");

        $response->assertInertia(fn ($page) => $page->where('canComplete', false));
        $this->assertSame(PurchaseOrder::STATUS_PARTIAL, $order->fresh()->status);
    }
}
