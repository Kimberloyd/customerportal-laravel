<?php

namespace Tests\Feature\Products;

use App\Models\AdminAudit;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($user)->get('/products/create')->assertStatus(403);
        $this->actingAsUser($user)->post('/products', [
            'product_name' => 'Widget',
            'unit_price' => 1,
        ])->assertStatus(403);
    }

    public function test_category_normalizes_and_falls_back_to_others(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)->post('/products', [
            'product_name' => 'Widget',
            'category' => 'open',
            'unit_price' => 1,
        ]);
        $this->assertSame('OPEN', Product::first()->category);

        $this->actingAsUser($staff)->post('/products', [
            'product_name' => 'Gadget',
            'category' => 'not-a-real-category',
            'unit_price' => 1,
        ]);
        $this->assertSame('OTHERS', Product::where('product_name', 'Gadget')->first()->category);
    }

    public function test_negative_price_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAsUser($staff)->post('/products', [
            'product_name' => 'Widget',
            'unit_price' => -1,
        ]);

        $response->assertSessionHasErrors('unit_price');
        $this->assertSame(0, Product::count());
    }

    public function test_non_numeric_price_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAsUser($staff)->post('/products', [
            'product_name' => 'Widget',
            'unit_price' => 'not-a-number',
        ]);

        $response->assertSessionHasErrors('unit_price');
    }

    public function test_price_too_large_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAsUser($staff)->post('/products', [
            'product_name' => 'Widget',
            'unit_price' => '10000000000',
        ]);

        $response->assertSessionHasErrors('unit_price');
    }

    public function test_gen_sku_uniqueness_rejects_duplicate_normalized_pair(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->actingAsUser($staff)->post('/products', [
            'sku' => 'GEN-1',
            'product_name' => 'Amoxicillin',
            'generic_name' => 'Amoxicillin Trihydrate',
            'unit_price' => 1,
        ]);

        $response = $this->actingAsUser($staff)->post('/products', [
            'sku' => 'GEN-2',
            'product_name' => '  amoxicillin  ',
            'generic_name' => 'amoxicillin trihydrate',
            'unit_price' => 1,
        ]);

        $response->assertSessionHasErrors('sku');
        $this->assertSame(1, Product::count());
    }

    public function test_gen_sku_uniqueness_does_not_apply_to_non_gen_products(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->actingAsUser($staff)->post('/products', [
            'sku' => 'STD-1',
            'product_name' => 'Amoxicillin',
            'generic_name' => 'Amoxicillin Trihydrate',
            'unit_price' => 1,
        ]);

        $response = $this->actingAsUser($staff)->post('/products', [
            'sku' => 'STD-2',
            'product_name' => 'Amoxicillin',
            'generic_name' => 'Amoxicillin Trihydrate',
            'unit_price' => 1,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(2, Product::count());
    }

    public function test_plain_sku_uniqueness_enforced(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeProduct('First', ['sku' => 'DUP-1']);

        $response = $this->actingAsUser($staff)->post('/products', [
            'sku' => 'DUP-1',
            'product_name' => 'Second',
            'unit_price' => 1,
        ]);

        $response->assertSessionHasErrors('sku');
    }

    public function test_writes_audit_row_with_exact_details_format(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)->post('/products', [
            'sku' => 'ABC-1',
            'product_name' => 'Widget',
            'unit_price' => 1,
        ]);

        $audit = AdminAudit::first();
        $this->assertSame('product', $audit->entity_type);
        $this->assertSame('created', $audit->action);
        $this->assertSame('product_name=Widget, sku=ABC-1', $audit->details);
        $this->assertSame($staff->id, $audit->actor_user_id);
    }
}
