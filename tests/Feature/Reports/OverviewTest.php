<?php

namespace Tests\Feature\Reports;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function seedOrders()
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $completed = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now()->subDays(10), [
            ['product_id' => $product->id, 'quantity' => 10, 'delivered_quantity' => 10],
        ]);
        $completed->update(['completed_at' => now()->subDays(2)]);

        $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now()->subDays(5), [
            ['product_id' => $product->id, 'quantity' => 6, 'delivered_quantity' => 2],
        ]);

        $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, now()->subDays(3), [
            ['product_id' => $product->id, 'quantity' => 100, 'delivered_quantity' => 0],
        ]);

        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDays(1), [
            ['product_id' => $product->id, 'quantity' => 4, 'delivered_quantity' => 0],
        ]);

        return $customer;
    }

    public function test_fulfillment_metrics_match_hand_computed_values(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->seedOrders();

        $response = $this->actingAsUser($staff)->get('/reports/overview');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('metrics.ordered_units', 20)
            ->where('metrics.delivered_units', 12)
            ->where('metrics.backlog_units', 8)
            ->where('metrics.fulfillment_rate', 60)
            ->where('metrics.completion_rate', 33.3)
            ->where('metrics.average_completion_days', 8)
        );
    }

    public function test_average_completion_uses_completed_at_not_updated_at(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now()->subDays(10), [
            ['product_id' => $product->id, 'quantity' => 5, 'delivered_quantity' => 5],
        ]);
        $order->update(['completed_at' => now()->subDays(8)]);
        // A later remarks edit bumps updated_at without touching
        // completed_at -- must not affect the reported duration.
        $order->update(['remarks' => 'edited later', 'updated_at' => now()]);

        $response = $this->actingAsUser($staff)->get('/reports/overview');

        $response->assertInertia(fn ($page) => $page->where('metrics.average_completion_days', 2));
    }

    public function test_status_mix_counts_all_statuses_including_cancelled(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->seedOrders();

        $response = $this->actingAsUser($staff)->get('/reports/overview');

        $response->assertInertia(function ($page) {
            $statusMix = $page->toArray()['props']['statusMix'];
            $byKey = collect($statusMix)->keyBy('key');
            $this->assertSame(1, $byKey['submitted']['count']);
            $this->assertSame(1, $byKey['partial']['count']);
            $this->assertSame(1, $byKey['completed']['count']);
            $this->assertSame(1, $byKey['cancelled']['count']);
        });
    }

    public function test_backlog_aging_buckets_place_orders_correctly(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->seedOrders();

        $response = $this->actingAsUser($staff)->get('/reports/overview');

        $response->assertInertia(function ($page) {
            $rows = collect($page->toArray()['props']['agingRows'])->keyBy('label');
            // D: submitted, age 1 day, balance 4 -- falls in 0-2 days.
            $this->assertSame(1, $rows['0-2 days']['orders']);
            $this->assertSame(4, $rows['0-2 days']['units']);
            // B: partial, age 5 days, balance 4 -- falls in 3-7 days.
            $this->assertSame(1, $rows['3-7 days']['orders']);
            $this->assertSame(4, $rows['3-7 days']['units']);
            $this->assertSame(0, $rows['8-14 days']['orders']);
            $this->assertSame(0, $rows['15+ days']['orders']);
        });
    }

    public function test_customer_viewer_is_scoped_and_hides_customer_performance(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $own = $this->makeCustomer('Own Co', $user);
        $other = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();

        $this->makeOrder($own, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 3],
        ]);
        $this->makeOrder($other, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 50],
        ]);

        $response = $this->actingAsUser($user)->get('/reports/overview');

        $response->assertInertia(fn ($page) => $page
            ->where('isCustomerView', true)
            ->where('metrics.ordered_units', 3)
            ->where('customerPerformance', [])
        );
    }

    public function test_orphaned_customer_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($user)->get('/reports/overview')->assertStatus(403);
    }
}
