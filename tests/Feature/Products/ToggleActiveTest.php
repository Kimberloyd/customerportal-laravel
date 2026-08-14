<?php

namespace Tests\Feature\Products;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ToggleActiveTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_flips_is_active(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $product = $this->makeProduct();
        $this->assertTrue($product->is_active);

        $this->actingAsUser($staff)->post("/products/{$product->id}/toggle-active");
        $this->assertFalse($product->fresh()->is_active);

        $this->actingAsUser($staff)->post("/products/{$product->id}/toggle-active");
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->makeProduct();

        $this->actingAsUser($user)->post("/products/{$product->id}/toggle-active")->assertStatus(403);
    }

    public function test_audit_action_reflects_direction(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $product = $this->makeProduct();

        $this->actingAsUser($staff)->post("/products/{$product->id}/toggle-active");
        $this->assertSame('deactivated', AdminAudit::latest('id')->first()->action);

        $this->actingAsUser($staff)->post("/products/{$product->id}/toggle-active");
        $this->assertSame('reactivated', AdminAudit::latest('id')->first()->action);
    }

    public function test_deactivated_product_disappears_from_po_create_picker(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $product = $this->makeProduct('Visible Product');

        $before = $this->actingAsUser($staff)->get('/purchase-orders/create');
        $before->assertInertia(fn ($page) => $page->has('products', 1));

        $this->actingAsUser($staff)->post("/products/{$product->id}/toggle-active");

        $after = $this->actingAsUser($staff)->get('/purchase-orders/create');
        $after->assertInertia(fn ($page) => $page->has('products', 0));
    }
}
