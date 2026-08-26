<?php

namespace App\Support;

use App\Models\CustomerMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ports app/facebook_messenger.py's outbound/profile pieces. Sending is
 * a real call to the Meta Graph API's `/me/messages` endpoint, gated by
 * the same "unconfigured = skip" pattern as App\Support\OrderNotifications's
 * email/Messenger stubs -- environments with no META_MESSENGER_* credentials
 * silently no-op instead of failing.
 */
class FacebookMessenger
{
    public static function isConfigured(): bool
    {
        return (bool) config('services.facebook.page_access_token');
    }

    /**
     * @throws MessengerApiException when configured but the send fails
     * @return string|null the Graph API message id, or null when unconfigured
     */
    public static function sendReply(CustomerMessage $thread, string $body): ?string
    {
        if (! self::isConfigured()) {
            Log::info("Facebook reply skipped for thread {$thread->id}: Messenger API not configured in this environment.");

            return null;
        }

        if (! $thread->external_sender_id) {
            throw new MessengerApiException("Thread {$thread->id} has no Facebook recipient to reply to.");
        }

        $version = config('services.facebook.graph_api_version', 'v19.0');
        $token = config('services.facebook.page_access_token');
        $url = "https://graph.facebook.com/{$version}/me/messages?".http_build_query(['access_token' => $token]);

        try {
            $response = Http::asJson()->timeout(10)->post($url, [
                'recipient' => ['id' => $thread->external_sender_id],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => $body],
            ]);
        } catch (\Throwable $e) {
            report($e);

            throw new MessengerApiException('Unable to reach the Facebook Messenger API.', previous: $e);
        }

        $error = $response->json('error.message');
        if ($error || $response->failed()) {
            $message = $error ?? "Facebook Messenger API returned HTTP {$response->status()}.";
            Log::warning("Facebook reply failed for thread {$thread->id}: {$message}");

            throw new MessengerApiException($message);
        }

        return $response->json('message_id');
    }

    /**
     * @return bool whether the sender's cached name changed
     */
    public static function refreshSenderProfile(CustomerMessage $thread): bool
    {
        return false;
    }
}
