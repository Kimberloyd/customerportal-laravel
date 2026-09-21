<?php

namespace Tests\Feature;

use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use App\Support\OrderNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class FacebookOrderSummaryTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.po_notifications.facebook_enabled' => true,
            'services.facebook.page_access_token' => 'test-token',
        ]);
    }

    public function test_a_closed_24_hour_window_is_recorded_as_skipped_not_failed(): void
    {
        [$order, $thread] = $this->orderAndAgentThread();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => [
                'message' => '(#10) This message is sent outside of allowed window.',
                'type' => 'OAuthException',
                'code' => 10,
                'error_subcode' => 2018278,
            ],
        ], 400)]);

        OrderNotifications::deliver($order, 'submitted');

        $row = PurchaseOrderNotification::where('channel', 'facebook')->firstOrFail();
        $this->assertSame('skipped', $row->status);
        $this->assertSame((string) $thread->id, $row->recipient);
        $this->assertStringContainsString('24 hours', $row->note);
        // Nothing was delivered, so no reply may appear in the conversation.
        $this->assertSame(0, CustomerMessage::where('parent_id', $thread->id)->count());
    }

    public function test_any_other_messenger_error_is_still_recorded_as_failed(): void
    {
        [$order] = $this->orderAndAgentThread();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => '(#100) Invalid parameter', 'type' => 'OAuthException', 'code' => 100],
        ], 400)]);

        OrderNotifications::deliver($order, 'submitted');

        $row = PurchaseOrderNotification::where('channel', 'facebook')->firstOrFail();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('Invalid parameter', $row->note);
    }

    public function test_a_delivered_summary_is_still_recorded_as_sent(): void
    {
        [$order] = $this->orderAndAgentThread();
        Http::fake(['graph.facebook.com/*' => Http::response(['recipient_id' => 'PSID-1', 'message_id' => 'm_abc'])]);

        OrderNotifications::deliver($order, 'submitted');

        $this->assertSame('sent', PurchaseOrderNotification::where('channel', 'facebook')->value('status'));
    }

    /** @return array{PurchaseOrder, CustomerMessage} */
    private function orderAndAgentThread(): array
    {
        $customerUser = User::factory()->create(['role' => 'customer']);
        $agent = User::factory()->create(['role' => 'agent']);
        $order = $this->makeOrder($this->makeCustomer('Acme Co', $customerUser), PurchaseOrder::STATUS_SUBMITTED, now());
        $thread = $this->makeThread(null, [
            'channel' => 'facebook_messenger',
            'assigned_user_id' => $agent->id,
            'external_sender_id' => 'PSID-1',
        ]);

        return [$order, $thread];
    }
}
