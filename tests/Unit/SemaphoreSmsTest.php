<?php

namespace Tests\Unit;

use App\Support\SemaphoreSms;
use App\Support\SmsApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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

    public function test_account_returns_the_credit_balance(): void
    {
        Cache::flush();
        config(['services.semaphore.api_key' => 'test-key']);
        Http::fake([
            'api.semaphore.co/api/v4/account*' => Http::response([
                'account_id' => 7,
                'account_name' => 'TheoMeds',
                'status' => 'Active',
                'credit_balance' => 1450,
            ]),
        ]);

        $account = SemaphoreSms::account();

        $this->assertSame(1450, $account['credit_balance']);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.semaphore.co/api/v4/account')
            && $request['apikey'] === 'test-key');
    }

    public function test_transactions_hits_the_account_scoped_route(): void
    {
        Cache::flush();
        config(['services.semaphore.api_key' => 'test-key']);
        Http::fake([
            'api.semaphore.co/*' => Http::response([
                ['id' => 1, 'amount' => 500, 'created_at' => '2026-08-01 09:00:00'],
            ]),
        ]);

        $transactions = SemaphoreSms::transactions(5);

        $this->assertCount(1, $transactions);
        // Transaction history lives under /account -- /api/v4/transactions is not a route.
        Http::assertSent(fn (Request $request) => str_starts_with(
            $request->url(),
            'https://api.semaphore.co/api/v4/account/transactions'
        ) && (int) $request['limit'] === 5);
    }

    public function test_reporting_calls_are_cached_so_the_rate_limit_is_respected(): void
    {
        Cache::flush();
        config(['services.semaphore.api_key' => 'test-key']);
        Http::fake(['api.semaphore.co/*' => Http::response(['credit_balance' => 10])]);

        SemaphoreSms::account();
        SemaphoreSms::account();

        // The /account family allows only 2 calls a minute, so a second read
        // inside the TTL must come from the cache, not the wire.
        Http::assertSentCount(1);
    }

    public function test_reporting_failures_return_null_instead_of_throwing(): void
    {
        Cache::flush();
        config(['services.semaphore.api_key' => 'test-key']);
        Http::fake(['api.semaphore.co/*' => Http::response('rate limited', 429)]);

        // A Semaphore outage must grey out the panel, not break the settings page.
        $this->assertNull(SemaphoreSms::account());
        $this->assertNull(SemaphoreSms::messages());
    }

    public function test_reporting_skips_entirely_when_not_configured(): void
    {
        config(['services.semaphore.api_key' => null]);

        $this->assertNull(SemaphoreSms::account());
        $this->assertNull(SemaphoreSms::transactions());
        $this->assertNull(SemaphoreSms::messages());
        Http::assertNothingSent();
    }

    public function test_messages_forwards_the_page_and_caches_per_page(): void
    {
        Cache::flush();
        config(['services.semaphore.api_key' => 'test-key']);
        Http::fake(['api.semaphore.co/*' => Http::response([['message_id' => 1]])]);

        SemaphoreSms::messages(6, 2);
        SemaphoreSms::messages(6, 3);
        // Page 2 again -- served from cache, so still two calls total.
        SemaphoreSms::messages(6, 2);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => (int) $request['page'] === 2
            && (int) $request['limit'] === 6);
        Http::assertSent(fn (Request $request) => (int) $request['page'] === 3);
    }
}
