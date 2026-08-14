<?php

namespace Tests\Feature\Customers;

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
        $customer = $this->makeCustomer();

        $this->actingAsUser($user)->get("/customers/{$customer->id}/edit")->assertStatus(403);
    }

    public function test_customer_code_never_changes_even_when_company_name_does(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('Original Name');
        $customer->update(['customer_code' => 'ORIG-1']);

        $this->actingAsUser($staff)->put("/customers/{$customer->id}", [
            'company_name' => 'Renamed Company',
            'channel' => 'HOSPITAL',
        ]);

        $customer->refresh();
        $this->assertSame('Renamed Company', $customer->company_name);
        $this->assertSame('ORIG-1', $customer->customer_code);
    }

    public function test_updates_contact_fields(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        $this->actingAsUser($staff)->put("/customers/{$customer->id}", [
            'company_name' => $customer->company_name,
            'channel' => 'PHARMACY',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
            'address' => '123 Main St',
        ]);

        $customer->refresh();
        $this->assertSame('PHARMACY', $customer->channel);
        $this->assertSame('Jane Doe', $customer->contact_person);
        $this->assertSame('jane@example.com', $customer->email);
        $this->assertSame('555-1234', $customer->phone);
        $this->assertSame('123 Main St', $customer->address);
    }
}
