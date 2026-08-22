<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Products (and the customers:sync source) come from the live
        // inventoryapp API, which three controllers call while handling a
        // request. Without this the suite makes real authenticated HTTPS
        // calls to production on nearly every test -- slow, dependent on
        // that service being up, and issuing real traffic from CI.
        //
        // Stray-request prevention makes any unfaked host fail loudly
        // rather than silently escaping to the network.
        Http::preventStrayRequests();
        $this->fakeInventoryApi();
    }

    /**
     * Stub the inventoryapp endpoints. Call again from a test with explicit
     * rows to exercise a populated catalogue -- the later fake wins.
     *
     * `has_next` must stay false: InventoryApiClient pages in a do/while
     * loop and would spin forever otherwise.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, array<string, mixed>>  $customers
     */
    protected function fakeInventoryApi(array $products = [], array $customers = []): void
    {
        Http::fake([
            '*/products*' => Http::response($this->inventoryPage($products)),
            '*/customers*' => Http::response($this->inventoryPage($customers)),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function inventoryPage(array $rows): array
    {
        return [
            'data' => $rows,
            'meta' => [
                'has_next' => false,
                'page' => 1,
                'limit' => 100,
                'total' => count($rows),
            ],
            'ok' => true,
        ];
    }

    /**
     * Plain actingAs() doesn't know about this app's session_version
     * convention (stamped at real login, see
     * AuthenticatedSessionController::store()) -- EnsureSessionVersionMatches
     * would otherwise see a session with no session_version at all,
     * treat that as a mismatch against the user's real value, and force
     * a logout on the very next request. Use this in place of bare
     * actingAs() for any test that needs to get past that middleware.
     */
    protected function actingAsUser(User $user, ?string $guard = null): static
    {
        $this->withSession(['session_version' => $user->session_version]);

        return $this->actingAs($user, $guard);
    }
}
