<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\OrderFollowUp;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ArchiveManagementTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_admin_can_view_the_archive_list(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->delete();
        $active = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $response = $this->actingAsUser($admin)->get(route('purchase-orders.archive'));

        $response->assertOk();
        $orders = collect($response->viewData('page')['props']['orders']['data']);
        $this->assertTrue($orders->contains('id', $order->id));
        $this->assertFalse($orders->contains('id', $active->id));
    }

    public function test_office_cannot_view_the_archive_list(): void
    {
        $office = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($office)->get(route('purchase-orders.archive'))->assertForbidden();
    }

    public function test_agent_cannot_view_the_archive_list(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAsUser($agent)->get(route('purchase-orders.archive'))->assertForbidden();
    }

    public function test_customer_cannot_view_the_archive_list(): void
    {
        $customerUser = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customerUser)->get(route('purchase-orders.archive'))->assertForbidden();
    }

    public function test_admin_can_restore_an_archived_order(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->delete();

        $response = $this->actingAsUser($admin)->post(route('purchase-orders.restore', $order->public_id));

        $response->assertRedirect(route('purchase-orders.archive'));
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('purchase_order_audits', [
            'purchase_order_id' => $order->id,
            'action' => 'Order Restored',
        ]);
    }

    public function test_agent_cannot_restore_an_archived_order(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $customer->update(['assigned_employee_id' => $agent->id]);
        $order->delete();

        $this->actingAsUser($agent)
            ->post(route('purchase-orders.restore', $order->public_id))
            ->assertForbidden();

        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
    }

    public function test_restore_404s_for_an_order_that_is_not_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($admin)
            ->post(route('purchase-orders.restore', $order->public_id))
            ->assertNotFound();
    }

    public function test_admin_can_permanently_delete_an_archived_order_and_everything_tied_to_it(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 2, 'delivered_quantity' => 1],
        ]);
        $item = $order->items->first();
        PurchaseOrderAudit::create(['purchase_order_id' => $order->id, 'action' => 'Order Created', 'created_at' => now()]);
        PurchaseOrderNotification::create(['purchase_order_id' => $order->id, 'channel' => 'portal', 'status' => 'sent', 'created_at' => now()]);
        OrderFollowUp::create([
            'purchase_order_id' => $order->id,
            'kind' => 'awaiting_fulfillment',
            'status' => 'pending',
            'level' => 'reminder',
            'cycle' => 1,
            'triggered_at' => now(),
            'next_due_at' => now()->addHours(4),
        ]);
        $return = ProductReturn::create([
            'purchase_order_id' => $order->id,
            'customer_id' => $customer->id,
            'status' => 'requested',
            'reason' => 'Damaged in transit',
            'requested_at' => now(),
        ]);
        $order->update(['po_file' => 'order-attachment.pdf']);
        Storage::disk('local')->put(\App\Support\PoAttachment::path('order-attachment.pdf'), 'attachment');
        $order->delete();

        $response = $this->actingAsUser($admin)->delete(route('purchase-orders.force-destroy', $order->public_id));

        $response->assertRedirect(route('purchase-orders.archive'));
        $this->assertDatabaseMissing('purchase_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('purchase_order_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('purchase_order_audits', ['purchase_order_id' => $order->id]);
        $this->assertDatabaseMissing('purchase_order_notifications', ['purchase_order_id' => $order->id]);
        $this->assertDatabaseMissing('order_follow_ups', ['purchase_order_id' => $order->id]);
        $this->assertDatabaseMissing('product_returns', ['id' => $return->id]);
        Storage::disk('local')->assertMissing(\App\Support\PoAttachment::path('order-attachment.pdf'));
    }

    public function test_permanent_delete_requires_the_order_to_be_archived_first(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($admin)
            ->delete(route('purchase-orders.force-destroy', $order->public_id))
            ->assertNotFound();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    public function test_agent_cannot_permanently_delete_an_order(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $customer->update(['assigned_employee_id' => $agent->id]);
        $order->delete();

        $this->actingAsUser($agent)
            ->delete(route('purchase-orders.force-destroy', $order->public_id))
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    public function test_admin_can_open_an_archived_order(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->delete();

        $response = $this->actingAsUser($admin)->get(route('purchase-orders.show', $order->public_id));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertTrue($props['order']['is_archived']);
        $this->assertTrue($props['canRestore']);
        $this->assertTrue($props['canDeleteForever']);
        $this->assertFalse($props['canManageFulfillment']);
        $this->assertFalse($props['canManageReturns']);
        $this->assertFalse($props['order']['can_edit_items']);
    }

    public function test_office_cannot_open_an_archived_order(): void
    {
        $office = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->delete();

        $this->actingAsUser($office)
            ->get(route('purchase-orders.show', $order->public_id))
            ->assertForbidden();
    }

    public function test_customer_cannot_open_their_own_archived_order(): void
    {
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $customerUser);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->delete();

        $this->actingAsUser($customerUser)
            ->get(route('purchase-orders.show', $order->public_id))
            ->assertForbidden();
    }

    public function test_a_non_archived_order_still_has_its_normal_capabilities(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $response = $this->actingAsUser($admin)->get(route('purchase-orders.show', $order->public_id));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertFalse($props['order']['is_archived']);
        $this->assertFalse($props['canRestore']);
        $this->assertFalse($props['canDeleteForever']);
        $this->assertTrue($props['order']['can_edit_items']);
    }
}
