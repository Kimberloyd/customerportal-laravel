<?php

namespace Tests\Feature\Customers;

use App\Models\AdminAudit;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_blocked_when_linked_user_still_exists(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $customerUser);

        $response = $this->actingAsUser($staff)->delete("/customers/{$customer->id}");

        $response->assertSessionHas('error', 'Delete the linked customer credentials before deleting this customer.');
        $this->assertNotNull(Customer::find($customer->id));
    }

    public function test_dangling_user_link_is_repaired_and_delete_proceeds(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $customerUser);
        $customerUser->delete();

        $response = $this->actingAsUser($staff)->delete("/customers/{$customer->id}");

        $response->assertRedirect(route('admin.dashboard', ['tab' => 'customers']));
        $this->assertNull(Customer::find($customer->id));
    }

    public function test_blocked_when_orders_exist(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->delete("/customers/{$customer->id}");

        $response->assertSessionHas('error', 'Customers with orders or message history cannot be deleted.');
        $this->assertNotNull(Customer::find($customer->id));
    }

    public function test_succeeds_for_customer_with_no_history(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        $response = $this->actingAsUser($staff)->delete("/customers/{$customer->id}");

        $response->assertRedirect(route('admin.dashboard', ['tab' => 'customers']));
        $this->assertNull(Customer::find($customer->id));
    }

    public function test_audit_row_survives_customer_deletion(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('Doomed Co');

        $this->actingAsUser($staff)->delete("/customers/{$customer->id}");

        $audit = AdminAudit::where('entity_type', 'customer')->where('action', 'deleted')->first();
        $this->assertNotNull($audit);
        $this->assertSame('company_name=Doomed Co', $audit->details);
    }

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer();

        $this->actingAsUser($user)->delete("/customers/{$customer->id}")->assertStatus(403);
    }
}
