<?php

namespace Tests\Feature\Products;

use App\Models\AdminAudit;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_blocked_when_product_has_order_history(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->delete("/products/{$product->id}");

        $response->assertSessionHas('error', 'Products with orders cannot be deleted.');
        $this->assertNotNull($product->fresh());
    }

    public function test_succeeds_for_product_with_no_history(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $product = $this->makeProduct();

        $response = $this->actingAsUser($staff)->delete("/products/{$product->id}");

        $response->assertRedirect(route('products.index'));
        $this->assertNull(Product::find($product->id));
    }

    public function test_audit_row_survives_product_deletion(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $product = $this->makeProduct('Doomed Product', ['sku' => 'DOOM-1']);

        $this->actingAsUser($staff)->delete("/products/{$product->id}");

        $audit = AdminAudit::where('entity_type', 'product')->where('action', 'deleted')->first();
        $this->assertNotNull($audit);
        $this->assertSame('product_name=Doomed Product, sku=DOOM-1', $audit->details);
        $this->assertNull(Product::find($product->id));
    }

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->makeProduct();

        $this->actingAsUser($user)->delete("/products/{$product->id}")->assertStatus(403);
    }
}
