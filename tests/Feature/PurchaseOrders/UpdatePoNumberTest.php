<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class UpdatePoNumberTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_staff_can_set_the_po_number_after_creation(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['po_number' => null]);

        $response = $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/po-number", [
            'po_number' => 'CUSTOMER-REF-001',
        ]);

        $response->assertRedirect(route('purchase-orders.show', $order->public_id));
        $this->assertSame('CUSTOMER-REF-001', $order->fresh()->po_number);
    }

    public function test_staff_can_change_an_existing_po_number(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['po_number' => 'OLD-REF']);

        $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/po-number", [
            'po_number' => 'NEW-REF',
        ]);

        $this->assertSame('NEW-REF', $order->fresh()->po_number);
    }

    public function test_staff_cannot_set_the_po_number_to_one_already_in_use(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['po_number' => null]);
        $taken = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $taken->update(['po_number' => 'ALREADY-TAKEN']);

        $response = $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/po-number", [
            'po_number' => 'ALREADY-TAKEN',
        ]);

        $response->assertSessionHas('error', 'This PO number is already in use.');
        $this->assertNull($order->fresh()->po_number);
    }

    public function test_blank_po_number_clears_it(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['po_number' => 'SOME-REF']);

        $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/po-number", [
            'po_number' => '   ',
        ]);

        $this->assertNull($order->fresh()->po_number);
    }

    public function test_po_number_can_be_set_on_a_completed_order(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now());
        $order->update(['po_number' => null]);

        $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/po-number", [
            'po_number' => 'LATE-REF',
        ]);

        $this->assertSame('LATE-REF', $order->fresh()->po_number);
    }

    public function test_records_an_audit_entry_when_the_po_number_changes(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['po_number' => null]);

        $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/po-number", [
            'po_number' => 'AUDIT-REF',
        ]);

        $this->assertSame(
            1,
            PurchaseOrderAudit::where('purchase_order_id', $order->id)->where('details', 'PO number set to AUDIT-REF.')->count(),
        );
    }

    public function test_no_audit_entry_when_the_po_number_is_unchanged(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['po_number' => 'SAME-REF']);

        $this->actingAsUser($staff)->patch("/orders/{$order->public_id}/po-number", [
            'po_number' => 'SAME-REF',
        ]);

        $this->assertSame(0, PurchaseOrderAudit::where('purchase_order_id', $order->id)->count());
    }

    public function test_customer_cannot_change_the_po_number_on_their_own_order(): void
    {
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $customerUser);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $order->update(['po_number' => null]);

        $this->actingAsUser($customerUser)
            ->patch("/orders/{$order->public_id}/po-number", ['po_number' => 'HACKED-PO'])
            ->assertForbidden();

        $this->assertNull($order->fresh()->po_number);
    }

    public function test_non_owning_agent_gets_403(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $otherCustomer = $this->makeCustomer();
        $order = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($agent)
            ->patch("/orders/{$order->public_id}/po-number", ['po_number' => 'SNEAKY'])
            ->assertForbidden();
    }
}
