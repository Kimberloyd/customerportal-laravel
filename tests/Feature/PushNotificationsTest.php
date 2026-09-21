<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use App\Models\PushToken;
use App\Models\User;
use App\Support\OrderNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();

        // A real (throwaway) RSA key, so the token request is signed for real.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        $this->keyPath = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->keyPath, json_encode([
            'project_id' => 'test-project',
            'client_email' => 'push@test-project.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ]));

        config([
            'services.fcm.credentials_path' => $this->keyPath,
            'services.po_notifications.push_enabled' => true,
        ]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->keyPath);
        parent::tearDown();
    }

    public function test_guests_cannot_register_a_token(): void
    {
        $this->postJson('/push-tokens', ['token' => 'abc'])->assertUnauthorized();
    }

    public function test_a_token_is_registered_for_the_signed_in_user_and_moves_when_the_phone_changes_hands(): void
    {
        $first = User::factory()->create(['role' => 'customer']);
        $second = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($first)->postJson('/push-tokens', ['token' => 'phone-token'])->assertCreated();
        $this->assertSame($first->id, PushToken::where('token', 'phone-token')->value('user_id'));

        $this->actingAsUser($second)->postJson('/push-tokens', ['token' => 'phone-token'])->assertCreated();
        $this->assertSame(1, PushToken::where('token', 'phone-token')->count());
        $this->assertSame($second->id, PushToken::where('token', 'phone-token')->value('user_id'));
    }

    public function test_a_user_cannot_remove_someone_elses_token(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        PushToken::create(['user_id' => $owner->id, 'token' => 'owner-token']);

        $this->actingAsUser($other)->deleteJson('/push-tokens', ['token' => 'owner-token'])->assertNoContent();

        $this->assertDatabaseHas('push_tokens', ['token' => 'owner-token']);
    }

    public function test_signing_out_removes_the_tokens_registered_in_that_session(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        // Laravel only accepts 40-character session ids.
        PushToken::create(['user_id' => $user->id, 'token' => 'this-session', 'session_id' => str_repeat('a', 40)]);
        PushToken::create(['user_id' => $user->id, 'token' => 'other-session', 'session_id' => str_repeat('b', 40)]);

        // Called directly: the test client issues a new session id per request,
        // so the id stored at registration couldn't be matched through HTTP.
        $store = app('session')->driver();
        $store->setId(str_repeat('a', 40));
        $store->start();
        $request = Request::create('/logout', 'POST');
        $request->setLaravelSession($store);

        app(AuthenticatedSessionController::class)->destroy($request);

        $this->assertDatabaseMissing('push_tokens', ['token' => 'this-session']);
        $this->assertDatabaseHas('push_tokens', ['token' => 'other-session']);
    }

    public function test_signing_out_of_all_devices_removes_every_token_of_the_user(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        PushToken::create(['user_id' => $user->id, 'token' => 'one']);
        PushToken::create(['user_id' => $user->id, 'token' => 'two']);
        PushToken::create(['user_id' => $other->id, 'token' => 'not-mine']);

        $this->actingAsUser($user)->post(route('logout.all'));

        $this->assertSame(0, PushToken::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('push_tokens', ['token' => 'not-mine']);
    }

    public function test_an_order_update_pushes_every_phone_of_the_customers_account(): void
    {
        [$order, $user] = $this->orderWithCustomerUser();
        PushToken::create(['user_id' => $user->id, 'token' => 'phone-one']);
        PushToken::create(['user_id' => $user->id, 'token' => 'phone-two']);
        $this->fakeFirebase();

        OrderNotifications::deliver($order, 'updated');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/projects/test-project/messages:send')
            && $request['message']['token'] === 'phone-one'
            && $request['message']['data']['url'] === '/orders/'.$order->public_id
            && str_contains($request['message']['notification']['body'], $order->po_number));
        Http::assertSent(fn ($request) => ($request['message']['token'] ?? null) === 'phone-two');
        $this->assertSame(2, PurchaseOrderNotification::where('channel', 'push')->where('status', 'sent')->count());
    }

    public function test_a_phone_that_uninstalled_the_app_is_forgotten(): void
    {
        [$order, $user] = $this->orderWithCustomerUser();
        PushToken::create(['user_id' => $user->id, 'token' => 'gone']);
        $this->fakeFirebase(sendStatus: 404);

        OrderNotifications::deliver($order, 'updated');

        $this->assertDatabaseMissing('push_tokens', ['token' => 'gone']);
    }

    public function test_nothing_is_sent_while_the_feature_is_off(): void
    {
        config(['services.po_notifications.push_enabled' => false]);
        [$order, $user] = $this->orderWithCustomerUser();
        PushToken::create(['user_id' => $user->id, 'token' => 'phone']);
        $this->fakeFirebase();

        OrderNotifications::deliver($order, 'updated');

        Http::assertNothingSent();
        $this->assertSame('skipped', PurchaseOrderNotification::where('channel', 'push')->value('status'));
    }

    public function test_nothing_is_sent_until_the_firebase_key_exists(): void
    {
        config(['services.fcm.credentials_path' => $this->keyPath.'.missing']);
        [$order, $user] = $this->orderWithCustomerUser();
        PushToken::create(['user_id' => $user->id, 'token' => 'phone']);
        $this->fakeFirebase();

        OrderNotifications::deliver($order, 'updated');

        Http::assertNothingSent();
        $this->assertSame('skipped', PurchaseOrderNotification::where('channel', 'push')->value('status'));
    }

    /** @return array{PurchaseOrder, User} */
    private function orderWithCustomerUser(): array
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $customer = $this->makeCustomer('Acme Co', $user);

        return [$this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()), $user];
    }

    private function fakeFirebase(int $sendStatus = 200): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'fcm.googleapis.com/*' => Http::response(
                $sendStatus === 200 ? ['name' => 'projects/test-project/messages/1'] : ['error' => ['status' => 'NOT_FOUND']],
                $sendStatus,
            ),
        ]);
    }
}
