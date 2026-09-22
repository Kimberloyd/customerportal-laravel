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

    public function test_a_manual_agent_reply_retries_with_the_human_agent_tag_once_the_window_is_closed(): void
    {
        [, $thread] = $this->orderAndAgentThread();
        $admin = User::factory()->create(['role' => 'admin']);
        $outsideWindow = ['error' => [
            'message' => '(#10) This message is sent outside of allowed window.',
            'type' => 'OAuthException', 'code' => 10, 'error_subcode' => 2018278,
        ]];
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push($outsideWindow, 400)
            ->push(['recipient_id' => 'PSID-1', 'message_id' => 'm_retry'])]);

        $response = $this->actingAsUser($admin)
            ->postJson(route('messages.widget.facebook.send', $thread->public_id), ['body' => 'Your order is on its way.']);
        $response->assertOk();

        Http::assertSent(fn ($request) => $request['messaging_type'] === 'MESSAGE_TAG' && $request['tag'] === 'HUMAN_AGENT');
        $this->assertDatabaseHas('customer_messages', [
            'parent_id' => $thread->id,
            'external_message_id' => 'm_retry',
        ]);
    }

    public function test_a_manual_agent_reply_past_the_7_day_tag_window_gets_a_clear_error(): void
    {
        [, $thread] = $this->orderAndAgentThread();
        $admin = User::factory()->create(['role' => 'admin']);
        $outsideWindow = ['error' => [
            'message' => '(#10) This message is sent outside of allowed window.',
            'type' => 'OAuthException', 'code' => 10, 'error_subcode' => 2018278,
        ]];
        Http::fake(['graph.facebook.com/*' => Http::sequence()->push($outsideWindow, 400)->push($outsideWindow, 400)]);

        $response = $this->actingAsUser($admin)
            ->postJson(route('messages.widget.facebook.send', $thread->public_id), ['body' => 'Your order is on its way.']);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['body' => ["It's been more than 7 days since this contact last messaged the Page, so Facebook Messenger won't deliver a reply here anymore."]]);
    }

    public function test_a_reply_is_told_the_human_agent_tag_needs_meta_approval(): void
    {
        [, $thread] = $this->orderAndAgentThread();
        $admin = User::factory()->create(['role' => 'admin']);
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['error' => [
                'message' => '(#10) This message is sent outside of allowed window.',
                'type' => 'OAuthException', 'code' => 10, 'error_subcode' => 2018278,
            ]], 400)
            ->push(['error' => [
                'message' => "(#100) Cannot tag messages with 'HUMAN_AGENT' without prior approval.",
                'type' => 'OAuthException', 'code' => 100,
            ]], 400)]);

        $response = $this->actingAsUser($admin)
            ->postJson(route('messages.widget.facebook.send', $thread->public_id), ['body' => 'Your order is on its way.']);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['body' => ["This contact hasn't messaged in over 24 hours. Replying that late needs a Facebook feature (Human Agent) that hasn't been approved for this Page yet -- ask an administrator to request it in Meta's App Review."]]);
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
