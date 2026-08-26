<?php

namespace App\Http\Controllers;

use App\Events\CustomerMessageSent;
use App\Models\Customer;
use App\Models\CustomerMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Ports the webhook side of app/facebook_messenger.py: Meta's
 * subscription-verification handshake (GET) and inbound event
 * ingestion (POST), HMAC-signature-verified. Structure only -- with
 * no real META_MESSENGER_* credentials configured in this environment
 * (see App\Support\FacebookMessenger), `verify()` and `receive()` can
 * be exercised structurally but not against a real Meta webhook call.
 */
class FacebookWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $verifyToken = config('services.facebook.webhook_verify_token');

        if (
            $verifyToken
            && $request->query('hub_mode') === 'subscribe'
            && $request->query('hub_verify_token') === $verifyToken
        ) {
            return response($request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): Response
    {
        $appSecret = config('services.facebook.app_secret');

        if (! $appSecret) {
            Log::info('Facebook webhook event received but no app secret is configured in this environment; ignoring.');

            return response('', 200);
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        if (! hash_equals($expected, $signature)) {
            return response('Invalid signature', 403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                $this->ingestEvent($entry, $event);
            }
        }

        return response('', 200);
    }

    private function ingestEvent(array $entry, array $event): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $pageId = $entry['id'] ?? null;
        $messageId = $event['message']['mid'] ?? null;
        $text = $event['message']['text'] ?? null;

        if (! $senderId || ! $pageId || ! $messageId || $text === null) {
            return;
        }

        if (CustomerMessage::where('external_message_id', $messageId)->exists()) {
            return;
        }

        $thread = CustomerMessage::whereNull('parent_id')
            ->where('channel', 'facebook_messenger')
            ->where('external_sender_id', $senderId)
            ->where('external_page_id', $pageId)
            ->first();

        $now = now();

        if (! $thread) {
            $created = CustomerMessage::create([
                'subject' => 'Facebook Messenger conversation',
                'body' => $text,
                'sender_type' => 'customer',
                'is_read' => false,
                'status' => 'open',
                'channel' => 'facebook_messenger',
                'external_sender_id' => $senderId,
                'external_page_id' => $pageId,
                'external_message_id' => $messageId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            CustomerMessageSent::dispatch($created->id, null);

            return;
        }

        \App\Support\MessageThread::createReply($thread, $text, 'customer', $messageId);
    }
}
