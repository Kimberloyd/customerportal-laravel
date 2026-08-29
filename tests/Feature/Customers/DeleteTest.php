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
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_blocked_when_linked_user_still_exists(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $customerUser);

        $response = $this->actingAsUser($staff)->delete("/customers/{$customer->id}");

        $response->assertSessionHas('error', 'This customer is linked to an account. Delete the linked account first, then try again.');
        $this->assertNotNull(Customer::find($customer->id));
    }

    public function test_dangling_user_link_is_repaired_and_delete_proceeds(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $customerUser);
        $customerUser->forceDelete();

        $response = $this->actingAsUser($staff)->delete("/customers/{$customer->id}");

        $response->assertRedirect(route('admin.dashboard', ['tab' => 'customers']));
        $this->assertNull(Customer::find($customer->id));
    }

    public function test_customer_cannot_be_deleted_while_linked_account_is_in_recovery_window(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Recoverable Co', $customerUser);
        $customerUser->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
            'purge_after' => now()->addDays(30),
        ])->save();
        $customerUser->delete();

        $response = $this->actingAsUser($staff)->delete("/customers/{$customer->id}");

        $response->assertSessionHas('error', 'This customer is linked to an account. Delete the linked account first, then try again.');
        $this->assertNotNull(Customer::find($customer->id));
        $this->assertSame($customerUser->id, $customer->fresh()->user_id);
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

        $response->assertSessionHas('error', 'This customer has orders or messages and cannot be deleted. Deactivate the customer instead.');
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
