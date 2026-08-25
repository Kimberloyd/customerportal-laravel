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

        // Resolved lazily: Http::fake() *merges* stubs and the first match
        // wins, so a stub registered here with a fixed payload would shadow
        // any later one. Reading the properties at request time instead lets
        // fixtures keep seeding the catalogue after setUp() has run.
        Http::fake([
            '*/products*' => fn () => Http::response($this->inventoryPage($this->inventoryProducts)),
            '*/customers*' => fn () => Http::response($this->inventoryPage($this->inventoryCustomers)),
        ]);
    }

    /**
     * Rows the faked catalogue endpoints will return, in the upstream API's
     * own field vocabulary.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $inventoryProducts = [];

    /** @var array<int, array<string, mixed>> */
    protected array $inventoryCustomers = [];

    /**
     * Seed what the faked inventoryapp endpoints return.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, array<string, mixed>>  $customers
     */
    protected function fakeInventoryApi(array $products = [], array $customers = []): void
    {
        $this->inventoryProducts = $products;
        $this->inventoryCustomers = $customers;
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
