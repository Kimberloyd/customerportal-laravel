<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_employee_gets_403(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->get('/admin')->assertStatus(403);
    }

    public function test_customer_gets_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)->get('/admin')->assertStatus(403);
    }

    public function test_counts_match_seeded_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = $this->makeCustomer();
        $this->makeCustomer('Second Co');
        $product = $this->makeProduct();
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $this->makeThread($customer, ['status' => 'open']);
        $this->makeThread($customer, ['status' => 'closed']);

        $response = $this->actingAsUser($admin)->get('/admin');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('totalCustomers', 2)
            ->where('totalProducts', 1)
            ->where('totalOrders', 1)
            ->where('openMessages', 1)
        );
    }

    public function test_recent_lists_capped_at_ten_and_ordered_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        for ($i = 0; $i < 12; $i++) {
            $this->makeCustomer("Customer {$i}");
        }
        for ($i = 0; $i < 12; $i++) {
            $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->addMinutes($i), [
                ['product_id' => $product->id, 'quantity' => 1],
            ]);
        }

        $response = $this->actingAsUser($admin)->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->has('recentCustomers', 10)
            ->has('recentOrders', 10)
        );

        // Most recently submitted order should be first.
        $latestOrder = PurchaseOrder::orderByDesc('submitted_at')->first();
        $response->assertInertia(fn ($page) => $page->where('recentOrders.0.po_number', $latestOrder->po_number));
    }

    public function test_open_messages_only_counts_root_open_threads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = $this->makeCustomer();
        $root = $this->makeThread($customer, ['status' => 'open']);
        // A reply (parent_id set) must not count toward "open conversations".
        $root->replies()->create([
            'customer_id' => $customer->id,
            'subject' => $root->subject,
            'body' => 'reply',
            'sender_type' => 'customer',
            'is_read' => true,
            'status' => 'open',
            'channel' => 'portal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAsUser($admin)->get('/admin');

        $response->assertInertia(fn ($page) => $page->where('openMessages', 1));
    }
}
