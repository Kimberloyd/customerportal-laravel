<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ListTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($user)->get('/customers')->assertStatus(403);
    }

    public function test_search_matches_across_fields(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeCustomer('Acme Hospital');
        $this->makeCustomer('Globex Pharmacy');

        $response = $this->actingAsUser($staff)->get('/customers?search=acme');

        $response->assertInertia(fn ($page) => $page
            ->where('customers.total', 1)
            ->where('customers.data.0.company_name', 'Acme Hospital')
        );
    }

    public function test_status_filter_defaults_to_active_only(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeCustomer('Active Co');
        $inactive = $this->makeCustomer('Inactive Co');
        $inactive->update(['is_active' => false]);

        $response = $this->actingAsUser($staff)->get('/customers');
        $response->assertInertia(fn ($page) => $page->where('customers.total', 1));

        $allResponse = $this->actingAsUser($staff)->get('/customers?status=all');
        $allResponse->assertInertia(fn ($page) => $page->where('customers.total', 2));
    }

    public function test_list_paginates(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        for ($i = 0; $i < 30; $i++) {
            $this->makeCustomer("Customer {$i}");
        }

        $response = $this->actingAsUser($staff)->get('/customers');

        $response->assertInertia(fn ($page) => $page
            ->where('customers.total', 30)
            ->where('customers.last_page', 2)
        );
    }
}
