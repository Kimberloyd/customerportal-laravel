<?php

namespace Tests\Feature\Admin\Users;

use App\Models\AdminAudit;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_a_customer_accounts_page(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'customer', 'full_name' => 'Jared Sipes DVM']);
        $customer = Customer::create(['company_name' => 'Adventist Hospital', 'is_active' => true, 'user_id' => $target->id]);
        AdminAudit::create([
            'entity_type' => 'user', 'entity_id' => $target->id, 'action' => 'created',
            'details' => 'email='.$target->email, 'actor_user_id' => $admin->id, 'actor_role' => 'admin',
            'created_at' => now(),
        ]);

        $response = $this->actingAsUser($admin)->get(route('admin.users.show', $target->public_id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('account.full_name', 'Jared Sipes DVM')
            ->where('account.linked_customer_name', 'Adventist Hospital')
            ->where('account.linked_customer_id', $customer->id)
            ->has('activity', 1)
        );
    }

    public function test_non_admin_cannot_view_the_account_page(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $target = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($agent)
            ->get(route('admin.users.show', $target->public_id))
            ->assertForbidden();
    }
}
