<?php

namespace Tests\Feature\Messages;

use Tests\TestCase;

class FacebookWebhookTest extends TestCase
{
    public function test_meta_can_verify_the_webhook_with_the_configured_token(): void
    {
        config(['services.facebook.webhook_verify_token' => 'verify-token']);

        $this->get('/webhooks/facebook/messenger?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'verify-token',
            'hub_challenge' => 'challenge-value',
        ]))
            ->assertOk()
            ->assertSeeText('challenge-value');
    }

    public function test_webhook_rejects_a_payload_with_an_invalid_signature(): void
    {
        config(['services.facebook.app_secret' => 'app-secret']);

        $this->call(
            'POST',
            '/webhooks/facebook/messenger',
            server: ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid'],
            content: json_encode(['entry' => []], JSON_THROW_ON_ERROR),
        )->assertForbidden();
    }
}
