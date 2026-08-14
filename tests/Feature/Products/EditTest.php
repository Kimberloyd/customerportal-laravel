<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->makeProduct();

        $this->actingAsUser($user)->get("/products/{$product->id}/edit")->assertStatus(403);
        $this->actingAsUser($user)->put("/products/{$product->id}", [
            'product_name' => 'Renamed',
            'unit_price' => 1,
        ])->assertStatus(403);
    }

    public function test_updates_fields(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $product = $this->makeProduct('Original', ['sku' => 'ORIG-1']);

        $this->actingAsUser($staff)->put("/products/{$product->id}", [
            'sku' => 'ORIG-1',
            'product_name' => 'Renamed Product',
            'category' => 'EXCLUSIVE',
            'unit_price' => 9.99,
        ]);

        $product->refresh();
        $this->assertSame('Renamed Product', $product->product_name);
        $this->assertSame('EXCLUSIVE', $product->category);
        $this->assertEquals(9.99, $product->unit_price);
    }

    public function test_editing_does_not_rewrite_existing_order_item_snapshot(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Original Name', ['sku' => 'ORIG-1']);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1, 'product_name' => 'Original Name', 'sku' => 'ORIG-1'],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)->put("/products/{$product->id}", [
            'sku' => 'ORIG-1',
            'product_name' => 'Renamed Later',
            'unit_price' => 1,
        ]);

        $this->assertSame('Original Name', $item->fresh()->product_name);
        $this->assertSame('Renamed Later', $product->fresh()->product_name);
    }
}
