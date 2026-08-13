<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $name = 'Widget'): Product
    {
        return Product::create(['product_name' => $name, 'is_active' => true]);
    }

    private function makeCustomer(string $name = 'Acme Co', ?User $user = null): Customer
    {
        return Customer::create([
            'company_name' => $name,
            'is_active' => true,
            'user_id' => $user?->id,
        ]);
    }

    private function makeOrder(Customer $customer, string $status, \DateTimeInterface|string $submittedAt, array $items = []): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'status' => $status,
            'submitted_at' => $submittedAt,
        ]);

        foreach ($items as $item) {
            PurchaseOrderItem::create(array_merge([
                'purchase_order_id' => $order->id,
            ], $item));
        }

        return $order;
    }

    public function test_staff_user_sees_global_kpis_and_customers_card(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 3, 'delivered_quantity' => 0],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $product->id, 'quantity' => 2, 'delivered_quantity' => 2],
        ]);

        $response = $this->actingAsUser($staff)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('kpis.total_orders', 2)
            ->where('kpis.active_orders', 1)
            ->where('kpis.completed_orders', 1)
            ->where('kpis.total_customers', 1)
        );
    }

    public function test_customer_role_user_only_sees_their_own_orders_and_no_customers_card(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $ownCustomer = $this->makeCustomer('Own Co', $user);
        $otherCustomer = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();

        $this->makeOrder($ownCustomer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);

        $response = $this->actingAsUser($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.total_orders', 1)
            ->where('kpis.total_customers', null)
        );
    }

    public function test_customer_role_user_with_no_linked_customer_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($user)->get('/dashboard')->assertStatus(403);
    }

    public function test_monthly_volume_counts_and_units_match_seeded_orders(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        // Two orders in January, one in February; only delivered units
        // count towards "units sold", and cancelled orders' units are
        // excluded entirely (but non-cancelled excluded-status orders
        // still count towards the order count).
        $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, '2026-01-05 10:00:00', [
            ['product_id' => $product->id, 'quantity' => 10, 'delivered_quantity' => 10],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, '2026-01-20 10:00:00', [
            ['product_id' => $product->id, 'quantity' => 4, 'delivered_quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, '2026-01-22 10:00:00', [
            ['product_id' => $product->id, 'quantity' => 99, 'delivered_quantity' => 0],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, '2026-02-10 10:00:00', [
            ['product_id' => $product->id, 'quantity' => 2, 'delivered_quantity' => 0],
        ]);

        $response = $this->actingAsUser($staff)->get('/dashboard?chart_range=custom&chart_start_date=2026-01-01&chart_end_date=2026-02-28');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $months = $page->toArray()['props']['monthlyVolume']['months'];
            $jan = collect($months)->firstWhere('full_label', 'January 2026');
            $feb = collect($months)->firstWhere('full_label', 'February 2026');

            $this->assertSame(3, $jan['count']);
            $this->assertSame(11, $jan['units']); // 10 + 1 delivered, cancelled order's units excluded
            $this->assertSame(1, $feb['count']);
            $this->assertSame(0, $feb['units']);
        });
    }

    public function test_top_products_includes_cancelled_orders_unlike_the_chart(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Only Product');

        $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, now(), [
            ['product_id' => $product->id, 'quantity' => 7, 'delivered_quantity' => 0],
        ]);

        $response = $this->actingAsUser($staff)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('topProducts.0.product_name', 'Only Product')
            ->where('topProducts.0.ordered_units', 7)
        );
    }

    public function test_pending_orders_filters_by_status_and_paginates(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        for ($i = 0; $i < 30; $i++) {
            $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
                ['product_id' => $product->id, 'quantity' => 1],
            ]);
        }
        $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $product->id, 'quantity' => 1, 'delivered_quantity' => 0],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $product->id, 'quantity' => 1, 'delivered_quantity' => 1],
        ]);

        $allResponse = $this->actingAsUser($staff)->get('/dashboard');
        $allResponse->assertInertia(fn ($page) => $page
            ->where('pendingOrders.total', 31)
            ->where('pendingOrders.last_page', 2)
        );

        $submittedOnly = $this->actingAsUser($staff)->get('/dashboard?pending_status=submitted');
        $submittedOnly->assertInertia(fn ($page) => $page->where('pendingOrders.total', 30));

        $partialOnly = $this->actingAsUser($staff)->get('/dashboard?pending_status=partial');
        $partialOnly->assertInertia(fn ($page) => $page->where('pendingOrders.total', 1));
    }
}
