<?php

namespace App\Support;

use App\Models\CustomerMessage;
use Illuminate\Support\Facades\Log;

/**
 * Ports app/facebook_messenger.py's outbound/profile pieces as stubs.
 * This sandbox has no live META_MESSENGER_* credentials to verify
 * delivery against, so these are no-op/log until real credentials
 * exist -- same "best-effort, unconfigured = skip" pattern as
 * App\Support\OrderNotifications's email/Messenger stubs. The data
 * model (channel/external_* columns, thread linking, sender-name
 * override) and the webhook endpoint shape are real; only the actual
 * Graph API calls are stubbed.
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

        // Not implemented yet -- no live Meta Graph API token available
        // in this environment to verify delivery against. Wire up the
        // real `POST https://graph.facebook.com/{version}/me/messages`
        // call here once real credentials exist.
        throw new MessengerApiException('Facebook Messenger sending is not yet configured in this environment.');
    }

    /**
     * @return bool whether the sender's cached name changed
     */
    public static function refreshSenderProfile(CustomerMessage $thread): bool
    {
        return false;
    }
}
