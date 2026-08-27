<?php

namespace Tests\Unit;

use App\Support\SemaphoreSms;
use App\Support\SmsApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemaphoreSmsTest extends TestCase
{
    public function test_send_skips_and_returns_null_when_not_configured(): void
    {
        config(['services.semaphore.api_key' => null]);

        $result = SemaphoreSms::send('09171234567', 'Hello');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_send_posts_to_the_messages_endpoint_and_returns_the_message_id(): void
    {
        config(['services.semaphore.api_key' => 'test-key', 'services.semaphore.sender_name' => 'THEOMEDS']);
        Http::fake([
            'api.semaphore.co/*' => Http::response([[
                'message_id' => 123456,
                'recipient' => '639171234567',
                'message' => 'Hello',
                'status' => 'Queued',
            ]]),
        ]);

        $messageId = SemaphoreSms::send('09171234567', 'Hello');

        $this->assertSame('123456', $messageId);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.semaphore.co/api/v4/messages'
                && $request['apikey'] === 'test-key'
                && $request['number'] === '09171234567'
                && $request['message'] === 'Hello'
                && $request['sendername'] === 'THEOMEDS';
        });
    }

    public function test_send_throws_when_semaphore_reports_a_failed_status(): void
    {
        config(['services.semaphore.api_key' => 'test-key']);
        Http::fake([
            'api.semaphore.co/*' => Http::response([[
                'message_id' => 1,
                'status' => 'Failed',
            ]]),
        ]);

        $this->expectException(SmsApiException::class);

        SemaphoreSms::send('09171234567', 'Hello');
    }

    public function test_send_throws_on_a_non_2xx_response(): void
    {
        config(['services.semaphore.api_key' => 'test-key']);
        Http::fake([
            'api.semaphore.co/*' => Http::response(['message' => 'Invalid API key'], 401),
        ]);

        $this->expectException(SmsApiException::class);

        SemaphoreSms::send('09171234567', 'Hello');
    }
}
