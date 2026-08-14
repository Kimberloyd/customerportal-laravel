<?php

namespace Tests\Feature\Customers;

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
        $customer = $this->makeCustomer();
        $this->assertTrue($customer->is_active);

        $this->actingAsUser($staff)->post("/customers/{$customer->id}/toggle-active");
        $this->assertFalse($customer->fresh()->is_active);

        $this->actingAsUser($staff)->post("/customers/{$customer->id}/toggle-active");
        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer();

        $this->actingAsUser($user)->post("/customers/{$customer->id}/toggle-active")->assertStatus(403);
    }

    public function test_audit_action_reflects_direction(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        $this->actingAsUser($staff)->post("/customers/{$customer->id}/toggle-active");
        $this->assertSame('deactivated', AdminAudit::latest('id')->first()->action);

        $this->actingAsUser($staff)->post("/customers/{$customer->id}/toggle-active");
        $this->assertSame('reactivated', AdminAudit::latest('id')->first()->action);
    }
}
