<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_customer_sees_their_own_order_count(): void
    {
        $customer = $this->makeCustomer('Fresh Co');
        $user = User::factory()->create(['role' => 'customer']);
        $customer->update(['user_id' => $user->id]);
        $this->makeOrder($customer, 'pending', now());
        $this->makeOrder($customer, 'completed', now());

        $this->actingAsUser($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('stats.order_count', 2)
                ->where('stats.managed_customer_count', null)
            );
    }

    public function test_agent_sees_managed_customer_count(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $this->makeCustomer('A Co')->update(['assigned_employee_id' => $agent->id]);
        $this->makeCustomer('B Co')->update(['assigned_employee_id' => $agent->id]);

        $this->actingAsUser($agent)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('stats.managed_customer_count', 2)
            );
    }

    public function test_recent_account_activity_is_scoped_to_the_current_user(): void
    {
        $user = User::factory()->create(['role' => 'office']);
        $otherUser = User::factory()->create(['role' => 'office']);

        AdminAudit::create([
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'action' => 'two_factor_enabled',
            'details' => 'two-factor authentication enabled',
            'actor_user_id' => $user->id,
            'actor_role' => 'office',
            'created_at' => now(),
        ]);
        AdminAudit::create([
            'entity_type' => 'user',
            'entity_id' => $otherUser->id,
            'action' => 'updated',
            'details' => 'not this user',
            'actor_user_id' => $otherUser->id,
            'actor_role' => 'office',
            'created_at' => now(),
        ]);

        $this->actingAsUser($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->has('activity', 1)
                ->where('activity.0.action', 'two_factor_enabled')
                ->where('activity.0.actor_name', $user->full_name)
            );
    }

    public function test_customer_sees_order_status_breakdown_and_top_products(): void
    {
        $customer = $this->makeCustomer('Fresh Co');
        $user = User::factory()->create(['role' => 'customer']);
        $customer->update(['user_id' => $user->id]);

        $this->makeOrder($customer, 'completed', now(), [
            ['product_name' => 'Amoxicillin 500mg', 'quantity' => 10, 'unit_price' => 5],
            ['product_name' => 'Paracetamol 500mg', 'quantity' => 3, 'unit_price' => 2],
        ]);
        $this->makeOrder($customer, 'completed', now(), [
            ['product_name' => 'Amoxicillin 500mg', 'quantity' => 8, 'unit_price' => 5],
        ]);
        $this->makeOrder($customer, 'cancelled', now());

        $this->actingAsUser($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('order_insights.total_orders', 3)
                ->where('order_insights.status_breakdown.0.status', 'completed')
                ->where('order_insights.status_breakdown.0.count', 2)
                ->where('order_insights.status_breakdown.0.percentage', 66.7)
                ->where('order_insights.status_breakdown.1.status', 'cancelled')
                ->where('order_insights.status_breakdown.1.percentage', 33.3)
                ->where('order_insights.top_products.0.product_name', 'Amoxicillin 500mg')
                ->where('order_insights.top_products.0.total_quantity', 18)
                ->where('order_insights.top_products.0.order_count', 2)
                ->where('order_insights.top_products.1.product_name', 'Paracetamol 500mg')
            );
    }

    public function test_archived_orders_are_excluded_from_order_insights(): void
    {
        $customer = $this->makeCustomer('Fresh Co');
        $user = User::factory()->create(['role' => 'customer']);
        $customer->update(['user_id' => $user->id]);

        $kept = $this->makeOrder($customer, 'completed', now(), [
            ['product_name' => 'Amoxicillin 500mg', 'quantity' => 5, 'unit_price' => 5],
        ]);
        $archived = $this->makeOrder($customer, 'completed', now(), [
            ['product_name' => 'Ibuprofen 200mg', 'quantity' => 99, 'unit_price' => 3],
        ]);
        $archived->delete();

        $this->actingAsUser($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('order_insights.total_orders', 1)
                ->has('order_insights.top_products', 1)
                ->where('order_insights.top_products.0.product_name', 'Amoxicillin 500mg')
            );
    }

    public function test_non_customer_roles_get_no_order_insights(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAsUser($agent)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('order_insights', null)
            );
    }

    public function test_activity_shows_the_admin_who_acted_on_the_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'full_name' => 'Ada Reyes']);
        $user = User::factory()->create(['role' => 'office']);

        AdminAudit::create([
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'action' => 'password reset',
            'details' => "email={$user->email}",
            'actor_user_id' => $admin->id,
            'actor_role' => 'admin',
            'created_at' => now(),
        ]);

        $this->actingAsUser($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('activity.0.actor_name', 'Ada Reyes')
                ->where('activity.0.actor_role', 'admin')
            );
    }
}
