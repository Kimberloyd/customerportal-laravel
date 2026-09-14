<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class UpdateRemarksTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_staff_can_update_remarks_on_a_non_terminal_order(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $response = $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => 'Deliver to the back entrance.',
        ]);

        $response->assertRedirect(route('purchase-orders.show', $order->public_id));
        $this->assertSame('Deliver to the back entrance.', $order->fresh()->remarks);
    }

    public function test_rejects_remarks_change_on_a_completed_order(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now());
        $order->update(['remarks' => 'Original note']);

        $response = $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => 'Trying to change a completed order.',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('Original note', $order->fresh()->remarks);
    }

    public function test_rejects_remarks_change_on_a_cancelled_order(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, now());
        $order->update(['remarks' => 'Original note']);

        $response = $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => 'Trying to change a cancelled order.',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('Original note', $order->fresh()->remarks);
    }

    public function test_blank_remarks_clears_the_field(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['remarks' => 'Existing note']);

        $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => '   ',
        ]);

        $this->assertNull($order->fresh()->remarks);
    }

    public function test_rejects_remarks_over_the_length_limit(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $response = $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => str_repeat('a', 5001),
        ]);

        $response->assertSessionHasErrors('remarks');
        $this->assertNull($order->fresh()->remarks);
    }

    public function test_records_an_audit_entry_when_remarks_actually_change(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => 'A new note.',
        ]);

        $this->assertSame(
            1,
            PurchaseOrderAudit::where('purchase_order_id', $order->id)->where('details', 'Remarks updated.')->count(),
        );
    }

    public function test_no_audit_entry_when_remarks_are_unchanged(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['remarks' => 'Same note']);

        $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => 'Same note',
        ]);

        $this->assertSame(0, PurchaseOrderAudit::where('purchase_order_id', $order->id)->count());
    }

    public function test_customer_can_update_remarks_on_their_own_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $response = $this->actingAsUser($user)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => 'Please deliver after 2pm.',
        ]);

        $response->assertRedirect(route('purchase-orders.show', $order->public_id));
        $this->assertSame('Please deliver after 2pm.', $order->fresh()->remarks);
    }

    public function test_non_owning_customer_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $otherCustomer = $this->makeCustomer();
        $order = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($user)->patch("/orders/{$order->public_id}/remarks", [
            'remarks' => 'Trying to edit someone else\'s order.',
        ])->assertForbidden();
    }
}
