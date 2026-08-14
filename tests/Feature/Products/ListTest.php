<?php

namespace Tests\Feature\Products;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ListTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_role_can_view_but_sees_no_management_flag(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeProduct('Widget');

        $response = $this->actingAsUser($user)->get('/products');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('canManage', false));
    }

    public function test_staff_sees_management_flag(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAsUser($staff)->get('/products');

        $response->assertInertia(fn ($page) => $page->where('canManage', true));
    }

    public function test_search_filters_across_fields(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeProduct('Amoxicillin', ['sku' => 'AMX-1']);
        $this->makeProduct('Paracetamol', ['sku' => 'PARA-1']);

        $response = $this->actingAsUser($staff)->get('/products?search=amox');

        $response->assertInertia(fn ($page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.product_name', 'Amoxicillin')
        );
    }

    public function test_source_filter_generic_matches_gen_prefixed_sku_only(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeProduct('Generic Alias', ['sku' => 'GEN-1']);
        $this->makeProduct('Branded', ['sku' => 'BR-1']);

        $response = $this->actingAsUser($staff)->get('/products?source=generic');

        $response->assertInertia(fn ($page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.product_name', 'Generic Alias')
        );
    }

    public function test_status_filter_defaults_to_active_only(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeProduct('Active One');
        $this->makeProduct('Inactive One', ['is_active' => false]);

        $response = $this->actingAsUser($staff)->get('/products');

        $response->assertInertia(fn ($page) => $page->where('products.total', 1));

        $allResponse = $this->actingAsUser($staff)->get('/products?status=all');
        $allResponse->assertInertia(fn ($page) => $page->where('products.total', 2));
    }

    public function test_sort_by_brand_desc_orders_correctly(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeProduct('Alpha');
        $this->makeProduct('Zulu');

        $response = $this->actingAsUser($staff)->get('/products?sort_by=brand&sort_dir=desc');

        $response->assertInertia(fn ($page) => $page->where('products.data.0.product_name', 'Zulu'));
    }

    public function test_list_paginates(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        for ($i = 0; $i < 30; $i++) {
            $this->makeProduct("Product {$i}");
        }

        $response = $this->actingAsUser($staff)->get('/products');

        $response->assertInertia(fn ($page) => $page
            ->where('products.total', 30)
            ->where('products.last_page', 2)
        );
    }
}
