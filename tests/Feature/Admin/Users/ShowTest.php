<?php

namespace Tests\Feature\Admin\Users;

use App\Models\AdminAudit;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_admin_can_view_a_customer_accounts_page_with_order_insights(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'customer', 'full_name' => 'Jared Sipes DVM']);
        $customer = $this->makeCustomer('Adventist Hospital', $target);
        $product = $this->makeProduct('Amoxicillin');
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
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
            ->where('account.order_count', 1)
            ->where('order_insights.total_orders', 1)
            ->where('order_insights.top_products.0.product_name', 'Amoxicillin')
            ->has('activity', 1)
        );
    }

    public function test_staff_accounts_have_no_order_insights(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'agent']);

        $response = $this->actingAsUser($admin)->get(route('admin.users.show', $target->public_id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('order_insights', null));
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
