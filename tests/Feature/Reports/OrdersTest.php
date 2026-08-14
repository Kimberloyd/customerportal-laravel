<?php

namespace Tests\Feature\Reports;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_month_filter_narrows_to_that_month(): void
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

        $response = $this->actingAsUser($staff)->get('/reports/orders?date_filter=month&month=2026-03');

        $response->assertInertia(fn ($page) => $page->where('summary.orders', 1));
    }

    public function test_custom_range_filter(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, '2026-03-05 10:00:00', [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, '2026-05-05 10:00:00', [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get('/reports/orders?date_filter=custom&start_date=2026-03-01&end_date=2026-03-31');

        $response->assertInertia(fn ($page) => $page->where('summary.orders', 1));
    }

    public function test_status_filter_partial_matches_in_progress_statuses(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_PROCESSING, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get('/reports/orders?status=partial');

        $response->assertInertia(fn ($page) => $page->where('summary.orders', 2));
    }

    public function test_customer_auto_scoped(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $own = $this->makeCustomer('Own Co', $user);
        $other = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();

        $this->makeOrder($own, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeOrder($other, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);

        $response = $this->actingAsUser($user)->get('/reports/orders');

        $response->assertInertia(fn ($page) => $page->where('summary.orders', 1));
    }

    public function test_summary_matches_full_filtered_set_not_just_visible_page(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        for ($i = 0; $i < 30; $i++) {
            $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
                ['product_id' => $product->id, 'quantity' => 2],
            ]);
        }

        $response = $this->actingAsUser($staff)->get('/reports/orders');

        $response->assertInertia(fn ($page) => $page
            ->where('summary.orders', 30)
            ->where('summary.ordered_units', 60)
            ->where('orders.total', 30)
            ->where('orders.last_page', 2)
            ->has('orders.data', 25)
        );
    }
}
