<?php

namespace Tests\Feature\PurchaseOrders;

use App\Events\PurchaseOrderChanged;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use App\Support\PoAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_admin_can_permanently_delete_an_order_and_its_related_records(): void
    {
        Event::fake([PurchaseOrderChanged::class]);
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
        $order->update(['po_file' => 'order-attachment.pdf']);
        Storage::disk('local')->put(PoAttachment::path('order-attachment.pdf'), 'attachment');
        PurchaseOrderAudit::create([
            'purchase_order_id' => $order->id,
            'action' => 'Order Created',
            'created_at' => now(),
        ]);
        PurchaseOrderNotification::create([
            'purchase_order_id' => $order->id,
            'channel' => 'portal',
            'status' => 'sent',
            'created_at' => now(),
        ]);

        $this->actingAsUser($admin)
            ->delete(route('purchase-orders.destroy', $order))
            ->assertRedirect(route('purchase-orders.index'))
            ->assertSessionHas('success', 'Order deleted permanently.');

        $this->assertDatabaseMissing('purchase_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('purchase_order_items', ['purchase_order_id' => $order->id]);
        $this->assertDatabaseMissing('purchase_order_audits', ['purchase_order_id' => $order->id]);
        $this->assertDatabaseMissing('purchase_order_notifications', ['purchase_order_id' => $order->id]);
        Storage::disk('local')->assertMissing(PoAttachment::path('order-attachment.pdf'));
        Event::assertDispatched(
            PurchaseOrderChanged::class,
            fn (PurchaseOrderChanged $event) => $event->orderId === $order->id
                && $event->change === 'deleted'
                && $event->previousCustomerId === $customer->id,
        );
    }

    public function test_non_administrators_cannot_delete_orders(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($employee)
            ->delete(route('purchase-orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }
}
