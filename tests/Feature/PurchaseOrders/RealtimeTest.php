<?php

namespace Tests\Feature\PurchaseOrders;

use App\Events\PurchaseOrderChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_users_can_only_authorize_their_own_private_channel_while_active(): void
    {
        $this->useReverbBroadcasterForChannelAuth();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAsUser($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$user->id}",
        ])->assertOk();

        $this->actingAsUser($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$otherUser->id}",
        ])->assertForbidden();
    }

    public function test_inactive_users_cannot_authorize_their_private_channel(): void
    {
        $this->useReverbBroadcasterForChannelAuth();

        $inactiveUser = User::factory()->inactive()->create();

        $this->actingAsUser($inactiveUser)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$inactiveUser->id}",
        ])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_order_event_targets_staff_current_customer_and_previous_customer_only(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $currentCustomerUser = User::factory()->customer()->create();
        $previousCustomerUser = User::factory()->customer()->create();
        User::factory()->customer()->create();
        User::factory()->inactive()->create();

        $currentCustomer = $this->makeCustomer('Current Customer', $currentCustomerUser);
        $previousCustomer = $this->makeCustomer('Previous Customer', $previousCustomerUser);
        $order = $this->makeOrder($currentCustomer, 'submitted', now());

        $event = new PurchaseOrderChanged($order->id, 'updated', $previousCustomer->id);
        $channels = collect($event->broadcastOn())->map->name->sort()->values()->all();

        $this->assertSame([
            "private-users.{$admin->id}",
            "private-users.{$employee->id}",
            "private-users.{$currentCustomerUser->id}",
            "private-users.{$previousCustomerUser->id}",
        ], collect($channels)->sort()->values()->all());
        $this->assertSame('purchase-order.changed', $event->broadcastAs());
        $this->assertSame($order->id, $event->broadcastWith()['order_id']);
        $this->assertSame('broadcasts', $event->queue);
    }

    public function test_creating_an_order_dispatches_the_realtime_event(): void
    {
        Event::fake([PurchaseOrderChanged::class]);

        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Realtime Product');

        $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-REALTIME',
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ])->assertRedirect(route('purchase-orders.index'));

        Event::assertDispatched(
            PurchaseOrderChanged::class,
            fn (PurchaseOrderChanged $event) => $event->change === 'created',
        );
    }

    private function useReverbBroadcasterForChannelAuth(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Broadcast::purge();
        require base_path('routes/channels.php');
    }
}
