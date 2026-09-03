<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Semaphore's (semaphore.co) standard SMS endpoint.
 * Mirrors FacebookMessenger's "unconfigured = skip, configured-but-failed =
 * throw" shape so OrderNotifications can treat every channel the same way.
 */
class SemaphoreSms
{
    private const ENDPOINT = 'https://api.semaphore.co/api/v4/messages';

    private const ACCOUNT_ENDPOINT = 'https://api.semaphore.co/api/v4/account';

    /**
     * Semaphore documents transaction history under /account, not at the
     * top level -- /api/v4/transactions is not a route.
     */
    private const TRANSACTIONS_ENDPOINT = 'https://api.semaphore.co/api/v4/account/transactions';

    /**
     * The /account family allows only 2 calls a minute, so a settings page
     * that fetched live on every render would 429 on the second refresh.
     * Everything here is reporting data that tolerates being a minute stale.
     */
    private const ACCOUNT_CACHE_TTL = 60;

    /** Message retrieval is the looser 30/min, but still worth not hammering. */
    private const MESSAGES_CACHE_TTL = 30;

    public static function isConfigured(): bool
    {
        return (bool) config('services.semaphore.api_key');
    }

    /**
     * @throws SmsApiException when configured but the send fails
     * @return string|null the Semaphore message id, or null when unconfigured
     */
    public static function send(string $number, string $message): ?string
    {
        if (! self::isConfigured()) {
            Log::info('SMS send skipped: Semaphore API not configured in this environment.');

            return null;
        }

        $payload = [
            'apikey' => config('services.semaphore.api_key'),
            'number' => $number,
            'message' => $message,
        ];

        if ($senderName = config('services.semaphore.sender_name')) {
            $payload['sendername'] = $senderName;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            report($e);

            throw new SmsApiException('Unable to reach the Semaphore SMS API.', previous: $e);
        }

        if ($response->failed()) {
            Log::warning("Semaphore SMS API returned HTTP {$response->status()}.");

            throw new SmsApiException("Semaphore SMS API returned HTTP {$response->status()}.");
        }

        // Standard sends return an array of message objects (one per
        // comma-separated recipient); a single number still comes back
        // wrapped in an array. A 2xx here only means Semaphore accepted the
        // message for queueing, not that it was delivered -- "Failed" is
        // reported inside this body, not via the HTTP status.
        $body = $response->json();
        $entry = is_array($body) ? ($body[0] ?? $body) : [];

        if (($entry['status'] ?? null) === 'Failed') {
            Log::warning("Semaphore SMS API reported a failed send to {$number}.");

            throw new SmsApiException("Semaphore reported the message to {$number} failed to send.");
        }

        return isset($entry['message_id']) ? (string) $entry['message_id'] : null;
    }

    /**
     * Account details including the remaining credit balance.
     *
     * @return array<string, mixed>|null null when unconfigured or unreachable
     */
    public static function account(): ?array
    {
        $body = self::read(self::ACCOUNT_ENDPOINT, [], 'semaphore.account', self::ACCOUNT_CACHE_TTL);

        // A single account object, but Semaphore has been seen to wrap it in
        // an array the way the send endpoint does.
        if (is_array($body) && array_is_list($body)) {
            $body = $body[0] ?? null;
        }

        return is_array($body) ? $body : null;
    }

    /**
     * Credit purchase/spend history, newest first.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function transactions(int $limit = 10): ?array
    {
        return self::readList(
            self::TRANSACTIONS_ENDPOINT,
            $limit,
            'semaphore.transactions.'.$limit,
            self::ACCOUNT_CACHE_TTL,
        );
    }

    /**
     * Recently sent messages, newest first.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function messages(int $limit = 10, int $page = 1): ?array
    {
        return self::readList(
            self::ENDPOINT,
            $limit,
            "semaphore.messages.{$limit}.{$page}",
            self::MESSAGES_CACHE_TTL,
            $page,
        );
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private static function readList(
        string $endpoint,
        int $limit,
        string $cacheKey,
        int $ttl,
        int $page = 1,
    ): ?array {
        // The API caps limit at 1000; anything above is a caller mistake, not
        // something to forward and have rejected.
        $limit = max(1, min(1000, $limit));
        $page = max(1, $page);
        $body = self::read($endpoint, ['limit' => $limit, 'page' => $page], $cacheKey, $ttl);

        return is_array($body) ? array_values(array_filter($body, 'is_array')) : null;
    }

    /**
     * Shared GET for the read-only reporting endpoints.
     *
     * Unlike send(), these never throw: they back a settings panel, and a
     * Semaphore outage should grey out a credit balance rather than take the
     * whole page down. Callers distinguish "no data" by the null.
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     */
    private static function read(string $endpoint, array $query, string $cacheKey, int $ttl): ?array
    {
        if (! self::isConfigured()) {
            return null;
        }

        // Wrapped so a failed lookup is cached too. Cache::remember treats a
        // bare null as a miss, which would let a 429 storm retry on every
        // page load -- exactly what the 2/min limit punishes.
        $cached = Cache::remember($cacheKey, $ttl, fn () => [
            'data' => self::request($endpoint, $query),
        ]);

        return $cached['data'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     */
    private static function request(string $endpoint, array $query): ?array
    {
        try {
            $response = Http::timeout(10)->get($endpoint, [
                'apikey' => config('services.semaphore.api_key'),
                ...$query,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if ($response->status() === 429) {
            // Nothing useful to do but wait -- the cached miss below keeps us
            // from immediately asking again.
            Log::warning('Semaphore API rate limit hit; reporting data will be stale.');

            return null;
        }

        if ($response->failed()) {
            // Deliberately not logging the body or the URL: both carry the
            // apikey query parameter.
            Log::warning("Semaphore API returned HTTP {$response->status()} for a reporting request.");

            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }
}
