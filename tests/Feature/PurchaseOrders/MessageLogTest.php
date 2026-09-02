<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class MessageLogTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_admin_can_view_an_orders_message_log(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder($this->makeCustomer(), PurchaseOrder::STATUS_SUBMITTED, now());

        PurchaseOrderNotification::create([
            'purchase_order_id' => $order->id,
            'channel' => 'sms',
            'status' => 'sent',
            'recipient' => '09171234567',
            'external_reference' => 'sms-123',
            'created_at' => now(),
        ]);
        PurchaseOrderNotification::create([
            'purchase_order_id' => $order->id,
            'channel' => 'email',
            'status' => 'sent',
            'recipient' => 'customer@example.com',
            'created_at' => now(),
        ]);

        $this->actingAsUser($admin)
            ->getJson(route('purchase-orders.message-log', $order))
            ->assertOk()
            ->assertJsonPath('order.po_number', $order->po_number)
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.channel', 'sms')
            ->assertJsonPath('entries.0.status', 'sent')
            ->assertJsonPath('entries.0.external_reference', 'sms-123');
    }

    public function test_employee_can_view_an_orders_message_log(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), PurchaseOrder::STATUS_SUBMITTED, now());
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)
            ->getJson(route('purchase-orders.message-log', $order))
            ->assertOk();
    }

    public function test_customers_cannot_view_an_orders_message_log(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), PurchaseOrder::STATUS_SUBMITTED, now());
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)
            ->getJson(route('purchase-orders.message-log', $order))
            ->assertForbidden();
    }

    public function test_order_list_enables_message_log_for_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($admin)
            ->get(route('purchase-orders.index'))
            ->assertInertia(fn ($page) => $page->where('canViewMessageLog', true));

        $this->actingAsUser($employee)
            ->get(route('purchase-orders.index'))
            ->assertInertia(fn ($page) => $page->where('canViewMessageLog', true));
    }
}
