<?php

namespace Tests\Unit;

use App\Models\CustomerMessage;
use App\Support\FacebookMessenger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookMessengerTest extends TestCase
{
    public function test_send_reply_uses_a_bearer_token_without_putting_it_in_the_url(): void
    {
        config([
            'services.facebook.page_access_token' => 'page-token',
            'services.facebook.graph_api_version' => 'v26.0',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.123']),
        ]);

        $thread = new CustomerMessage([
            'external_sender_id' => 'recipient-123',
        ]);
        $thread->id = 42;

        $messageId = FacebookMessenger::sendReply($thread, 'Order received.');

        $this->assertSame('mid.123', $messageId);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.facebook.com/v26.0/me/messages'
                && $request->hasHeader('Authorization', 'Bearer page-token')
                && $request['recipient']['id'] === 'recipient-123'
                && $request['messaging_type'] === 'RESPONSE'
                && $request['message']['text'] === 'Order received.';
        });
    }
}
