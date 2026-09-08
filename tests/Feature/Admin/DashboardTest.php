<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
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

    public function test_agent_gets_403(): void
    {
        $agent = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($agent)->get('/admin')->assertStatus(403);
    }

    public function test_office_gets_403(): void
    {
        $office = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($office)->get('/admin')->assertStatus(403);
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
            'role' => 'agent',
        ]);
        User::factory()->create([
            'full_name' => 'Other Account',
            'email' => 'other-account@example.com',
            'role' => 'agent',
        ]);

        $response = $this->actingAsUser($admin)
            ->get('/admin?tab=accounts&search=jane&role=agent');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('activeTab', 'accounts')
            ->where('filters.search', 'jane')
            ->where('filters.role', 'agent')
            ->has('accountForm.customers')
            ->missing('users')
            ->loadDeferredProps('accounts', fn ($deferred) => $deferred
                ->has('users.data', 1)
                ->where('users.data.0.email', 'jane-account@example.com')
                ->where('users.data.0.phone', '5551234567')
                ->where('users.data.0.linked_customer_id', null)
                ->where('users.data.0.is_self', false)
                ->missing('users.data.0.password_hash')));
    }

    public function test_admin_defers_the_customers_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->get('/admin?tab=customers');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('activeTab', 'customers')
            ->missing('customers')
            ->loadDeferredProps('customers', fn ($deferred) => $deferred->has('customers.data')));
    }

    public function test_admin_can_load_the_teams_tab_with_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);
        $availableAgent = User::factory()->create(['role' => 'agent']);
        User::factory()->create(['role' => 'office']);
        $team = Team::create(['name' => 'North Team']);
        $team->members()->attach($agent);

        $response = $this->actingAsUser($admin)->get('/admin?tab=teams');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('activeTab', 'teams')
            ->has('teams', 1)
            ->where('teams.0.name', 'North Team')
            ->missing('teams.0.members.0.email')
            ->has('agents', 1)
            ->where('agents.0.id', $availableAgent->id)
            ->missing('agents.0.email'));
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
