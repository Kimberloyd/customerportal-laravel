<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class PrintTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_non_owning_customer_cannot_view(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);
        $other = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();
        $order = $this->makeOrder($other, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($user)->get("/orders/{$order->id}/print")->assertStatus(403);
    }

    public function test_owning_customer_and_staff_can_view(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($user)->get("/orders/{$order->id}/print")->assertOk();

        $staff = User::factory()->create(['role' => 'employee']);
        $this->actingAsUser($staff)->get("/orders/{$order->id}/print")->assertOk();
    }

    public function test_output_and_auto_print_params_reach_the_page(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get("/orders/{$order->id}/print?output=pdf&auto_print=0");

        $response->assertInertia(fn ($page) => $page
            ->where('output', 'pdf')
            ->where('autoPrint', false)
        );
    }

    public function test_invalid_output_falls_back_to_printer(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get("/orders/{$order->id}/print?output=nonsense");

        $response->assertInertia(fn ($page) => $page->where('output', 'printer'));
    }
}
