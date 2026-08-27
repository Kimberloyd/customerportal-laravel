<?php

namespace App\Support;

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
}
