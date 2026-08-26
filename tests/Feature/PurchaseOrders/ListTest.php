<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ListTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_role_only_sees_their_own_orders(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $own = $this->makeCustomer('Own Co', $user);
        $other = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();

        $this->makeOrder($own, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($other, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($user)->get('/orders');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.customer_name', 'Own Co')
        );
    }

    public function test_orphaned_customer_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($user)->get('/orders')->assertStatus(403);
    }

    public function test_search_filters_by_customer_name(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $acme = $this->makeCustomer('Acme Co');
        $globex = $this->makeCustomer('Globex Inc');
        $product = $this->makeProduct();

        $this->makeOrder($acme, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($globex, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get('/orders?search=acme');

        $response->assertInertia(fn ($page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.customer_name', 'Acme Co')
        );
    }

    public function test_status_filter_active_includes_submitted_and_in_progress_only(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $product->id, 'quantity' => 1, 'delivered_quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get('/orders?status=active');

        $response->assertInertia(fn ($page) => $page->where('orders.total', 2));
    }

    public function test_month_date_filter_narrows_to_that_month(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, '2026-03-05 10:00:00', [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, '2026-04-05 10:00:00', [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get('/orders?date_filter=month&month=2026-03');

        $response->assertInertia(fn ($page) => $page->where('orders.total', 1));
    }

    public function test_list_paginates(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        for ($i = 0; $i < 30; $i++) {
            $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
                ['product_id' => $product->id, 'quantity' => 1],
            ]);
        }

        $response = $this->actingAsUser($staff)->get('/orders');

        $response->assertInertia(fn ($page) => $page
            ->where('orders.total', 30)
            ->where('orders.per_page', 10)
            ->has('orders.data', 10)
            ->where('orders.last_page', 3)
        );
    }
}
