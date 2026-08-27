<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The admin dashboard's KPI tiles and recent-activity lists were removed
 * when the page became a shell around the Products / Customers / Accounts
 * panels, so the tests counting those props went with them. What remains
 * is the access control, which still applies, plus a smoke test that the
 * page renders its default tab.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_gets_403(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->get('/admin')->assertStatus(403);
    }

    public function test_customer_gets_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)->get('/admin')->assertStatus(403);
    }

    public function test_admin_sees_the_products_tab_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->get('/admin');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('activeTab', 'products'));
    }

    public function test_admin_sees_the_filtered_accounts_tab(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'full_name' => 'Jane Account',
            'email' => 'jane-account@example.com',
            'phone' => '5551234567',
            'role' => 'employee',
        ]);
        User::factory()->create([
            'full_name' => 'Other Account',
            'email' => 'other-account@example.com',
            'role' => 'employee',
        ]);

        $response = $this->actingAsUser($admin)
            ->get('/admin?tab=accounts&search=jane&role=employee');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('activeTab', 'accounts')
            ->where('filters.search', 'jane')
            ->where('filters.role', 'employee')
            ->has('users.data', 1)
            ->where('users.data.0.email', 'jane-account@example.com')
            ->where('users.data.0.phone', '5551234567')
            ->where('users.data.0.linked_customer_id', null)
            ->where('users.data.0.is_self', false)
            ->missing('users.data.0.password_hash')
            ->where('accountForm.allowAdminCreation', false)
            ->has('accountForm.customers'));
    }

    public function test_product_search_uses_the_complete_catalog_without_an_upstream_query(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->fakeInventoryApi(products: [
            [
                'id' => 1,
                'sku' => 'ONE',
                'product_name' => 'First Product',
                'category' => 'Supplies',
                'generic' => 'First Generic',
                'description' => 'First description',
                'dosage' => null,
                'unit_type' => 'pcs',
                'current_price' => 10,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'sku' => 'TWO',
                'product_name' => 'Second Product',
                'category' => 'Supplies',
                'generic' => 'Second Generic',
                'description' => 'Second description',
                'dosage' => null,
                'unit_type' => 'pcs',
                'current_price' => 20,
                'is_active' => true,
            ],
        ]);

        $response = $this->actingAsUser($admin)->get('/admin?tab=products&search=second');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filters.search', 'second')
            ->missing('products')
            ->loadDeferredProps('catalog', fn ($deferred) => $deferred
                ->has('products', 2)
                ->missing('products.0.description')
                ->missing('products.1.description')));
        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), '/products') && ! isset($query['q']);
        });
    }
}
