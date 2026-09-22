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
     * @param  bool  $allowHumanAgentTag  Meta's HUMAN_AGENT tag lets a real person
     *                                    reply to a specific customer inquiry for up
     *                                    to 7 days after their last message, instead
     *                                    of the standard 24-hour window -- but Meta
     *                                    restricts it to genuine human replies, not
     *                                    automated notifications. Pass true only from
     *                                    a staff member manually sending a reply (see
     *                                    MessageController::widgetFacebookSend); the
     *                                    automated order-summary path in
     *                                    OrderNotifications must never pass true.
     * @return string|null the Graph API message id, or null when unconfigured
     *
     * @throws MessengerApiException when configured but the send fails
     */
    public static function sendReply(CustomerMessage $thread, string $body, bool $allowHumanAgentTag = false): ?string
    {
        if (! self::isConfigured()) {
            Log::info("Facebook reply skipped for thread {$thread->id}: Messenger API not configured in this environment.");

            return null;
        }

        if (! $thread->external_sender_id) {
            throw new MessengerApiException("Thread {$thread->id} has no Facebook recipient to reply to.");
        }

        try {
            return self::send($thread, [
                'recipient' => ['id' => $thread->external_sender_id],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => $body],
            ]);
        } catch (MessengerApiException $e) {
            if (! $allowHumanAgentTag || ! $e->isOutsideMessagingWindow()) {
                throw $e;
            }

            Log::info("Facebook reply for thread {$thread->id} retrying with the HUMAN_AGENT tag: the 24-hour window is closed.");

            return self::send($thread, [
                'recipient' => ['id' => $thread->external_sender_id],
                'messaging_type' => 'MESSAGE_TAG',
                'tag' => 'HUMAN_AGENT',
                'message' => ['text' => $body],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MessengerApiException
     */
    private static function send(CustomerMessage $thread, array $payload): string
    {
        $version = config('services.facebook.graph_api_version', 'v19.0');
        $token = config('services.facebook.page_access_token');
        $url = "https://graph.facebook.com/{$version}/me/messages";

        try {
            // Keep the Page access token out of the URL so it cannot leak
            // through reverse-proxy access logs or exception URLs.
            $response = Http::withToken($token)->asJson()->timeout(10)->post($url, $payload);
        } catch (\Throwable $e) {
            report($e);

            throw new MessengerApiException('Unable to reach the Facebook Messenger API.', previous: $e);
        }

        $error = $response->json('error.message');
        if ($error || $response->failed()) {
            $message = $error ?? "Facebook Messenger API returned HTTP {$response->status()}.";
            $subcode = $response->json('error.error_subcode');
            $exception = new MessengerApiException(
                $message,
                (int) $response->json('error.code'),
                subcode: is_numeric($subcode) ? (int) $subcode : null,
            );

            // A closed 24-hour window is Meta's rule working as designed, not a fault.
            if ($exception->isOutsideMessagingWindow()) {
                Log::info("Facebook reply not sent for thread {$thread->id}: outside Meta's 24-hour messaging window.");
            } else {
                Log::warning("Facebook reply failed for thread {$thread->id}: {$message}");
            }

            throw $exception;
        }

        $messageId = $response->json('message_id');

        if (! is_string($messageId) || $messageId === '') {
            throw new MessengerApiException('Facebook Messenger accepted the request but did not return a message reference.');
        }

        return $messageId;
    }

    /**
     * @return bool whether the sender's cached name changed
     */
    public static function refreshSenderProfile(CustomerMessage $thread): bool
    {
        return false;
    }
}
