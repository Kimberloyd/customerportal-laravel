<?php

namespace Tests\Feature\Reports;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Regression cover for the product-performance aggregate on the Reports
 * Overview page. It used to join the local `products` table, which the
 * 2026_08_18 migration dropped -- so the page threw a QueryException for
 * every visitor. The aggregate is now built from the product_name snapshot
 * stored on each order item.
 */
class ProductPerformanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_overview_loads_and_aggregates_by_snapshotted_product_name(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        // Same product across two orders -- these must fold into one row.
        $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now()->subDays(4), [
            ['product_name' => 'Amoxicillin', 'quantity' => 10, 'delivered_quantity' => 4],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDays(3), [
            ['product_name' => 'Amoxicillin', 'quantity' => 5, 'delivered_quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDays(2), [
            ['product_name' => 'Paracetamol', 'quantity' => 3, 'delivered_quantity' => 3],
        ]);

        $response = $this->actingAsUser($staff)->get('/reports/overview');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->has('productPerformance', 2);

            // Ordered by backlog descending: Amoxicillin has 15 ordered,
            // 5 delivered, so 10 outstanding against Paracetamol's zero.
            $page->where('productPerformance.0.product_name', 'Amoxicillin')
                ->where('productPerformance.0.ordered', 15)
                ->where('productPerformance.0.delivered', 5)
                ->where('productPerformance.0.backlog', 10)
                ->where('productPerformance.0.rate', 33);

            $page->where('productPerformance.1.product_name', 'Paracetamol')
                ->where('productPerformance.1.backlog', 0)
                ->where('productPerformance.1.rate', 100);
        });
    }

    public function test_cancelled_orders_are_excluded_from_the_aggregate(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, now()->subDays(2), [
            ['product_name' => 'Ignored Product', 'quantity' => 99, 'delivered_quantity' => 0],
        ]);

        $response = $this->actingAsUser($staff)->get('/reports/overview');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('productPerformance', 0));
    }

    public function test_orders_report_loads_when_the_period_actually_matches_orders(): void
    {
        // The dead `items.product` eager-load only threw once the query
        // matched at least one order, so an empty result set masked it.
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDay(), [
            ['product_name' => 'Amoxicillin', 'quantity' => 2, 'delivered_quantity' => 0],
        ]);

        $response = $this->actingAsUser($staff)->get('/reports/orders?date_filter=all');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('summary.orders', 1));
    }
}
